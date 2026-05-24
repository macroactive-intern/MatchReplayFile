<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReplayRequest;
use App\Http\Requests\StoreReplayShareRequest;
use App\Http\Requests\UpdateReplayRequest;
use App\Http\Resources\ReplayResource;
use App\Models\Replay;
use App\Models\ReplayShare;
use App\Services\ReplayService;
use App\Services\ReplayStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ReplayController extends Controller
{
    private const SHARE_ACCESS_DEDUPE_MINUTES = 10;

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $guildIds = $user->guilds()->pluck('guilds.id');
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $replays = Replay::query()
            ->where(function ($query) use ($user, $guildIds): void {
                $query->where('user_id', $user->getKey())
                    ->orWhereIn('guild_id', $guildIds);
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('game_version'), function ($query) use ($request): void {
                $query->where('game_version', $request->string('game_version'));
            })
            ->latest()
            ->paginate($perPage);

        return ReplayResource::collection($replays);
    }

    public function store(StoreReplayRequest $request, ReplayService $replayService): JsonResponse
    {
        $result = $replayService->uploadReplay($request->user(), $request->validated());

        return (new ReplayResource($result->replay))
            ->additional([
                'meta' => [
                    'duplicate' => $result->duplicate,
                ],
            ])
            ->response()
            ->setStatusCode($result->duplicate ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function show(Replay $replay): ReplayResource
    {
        Gate::authorize('view', $replay);

        return new ReplayResource($replay);
    }

    public function update(UpdateReplayRequest $request, Replay $replay): ReplayResource
    {
        Gate::authorize('update', $replay);

        $replay->update($request->validated());

        return new ReplayResource($replay);
    }

    public function destroy(Replay $replay): JsonResponse
    {
        Gate::authorize('delete', $replay);

        $replay->delete();

        return response()->json(null, 204);
    }

    public function download(Replay $replay): JsonResponse
    {
        Gate::authorize('download', $replay);

        return $this->signedReplayDownloadResponse($replay);
    }

    public function analytics(Replay $replay): JsonResponse
    {
        Gate::authorize('viewAnalytics', $replay);

        $today = now()->copy()->startOfDay();
        $start = $today->copy()->subDays(29);
        $end = $today->copy()->endOfDay();

        $counts = collect(range(29, 0))
            ->mapWithKeys(fn (int $daysAgo): array => [
                $today->copy()->subDays($daysAgo)->toDateString() => 0,
            ]);

        $rows = $replay->accessEvents()
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as count')
            ->whereBetween('occurred_at', [$start, $end])
            ->groupByRaw('DATE(occurred_at)')
            ->pluck('count', 'date');

        $rows->each(function (int|string $count, string $date) use ($counts): void {
            if ($counts->has($date)) {
                $counts->put($date, (int) $count);
            }
        });

        $accessCountByDay = $counts
            ->map(fn (int $count, string $date): array => [
                'date' => $date,
                'count' => $count,
            ])
            ->values();

        return response()->json([
            'data' => [
                'replay_id' => $replay->id,
                'from' => $start->toDateString(),
                'to' => $today->toDateString(),
                'total_access_count' => $counts->sum(),
                'access_count_by_day' => $accessCountByDay,
                'labels' => $accessCountByDay->pluck('date')->values(),
                'datasets' => [
                    [
                        'label' => 'Accesses',
                        'data' => $accessCountByDay->pluck('count')->values(),
                    ],
                ],
            ],
        ]);
    }

    public function share(StoreReplayShareRequest $request, Replay $replay): JsonResponse
    {
        Gate::authorize('update', $replay);

        $share = $replay->shares()->create([
            'shared_by' => $request->user()->getKey(),
            'scope' => $request->validated('scope'),
            'token' => (string) Str::uuid(),
            'expires_at' => now()->addHours((int) $request->validated('expiry_hours')),
        ]);

        return response()->json([
            'token' => $share->token,
            'expires_at' => $share->expires_at,
        ], 201);
    }

    public function shared(string $token): ReplayResource|JsonResponse
    {
        $share = $this->shareForToken($token);

        if ($share->expires_at->isPast()) {
            return $this->expiredShareResponse();
        }

        $this->recordShareAccess($share);

        return new ReplayResource($share->replay);
    }

    public function sharedDownload(string $token): JsonResponse
    {
        $share = $this->shareForToken($token);

        if ($share->expires_at->isPast()) {
            return $this->expiredShareResponse();
        }

        $expiresAt = now()->addMinutes(10)->min($share->expires_at);

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'api.replay-shares.download.signed',
                $expiresAt,
                ['share' => $share],
            ),
            'expires_at' => $expiresAt,
        ]);
    }

    public function downloadFile(Replay $replay)
    {
        $this->recordReplayAccess($replay);

        return Storage::disk(ReplayStorage::DISK)->download(
            $replay->stored_path,
            $replay->original_filename,
            ['Content-Type' => $replay->mime_type],
        );
    }

    public function downloadSharedFile(ReplayShare $share)
    {
        if ($share->expires_at->isPast()) {
            return $this->expiredShareResponse();
        }

        $this->recordShareAccess($share);

        return Storage::disk(ReplayStorage::DISK)->download(
            $share->replay->stored_path,
            $share->replay->original_filename,
            ['Content-Type' => $share->replay->mime_type],
        );
    }

    private function signedReplayDownloadResponse(Replay $replay): JsonResponse
    {
        $expiresAt = now()->addMinutes(10);

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'api.replays.download.signed',
                $expiresAt,
                ['replay' => $replay],
            ),
            'expires_at' => $expiresAt,
        ]);
    }

    private function shareForToken(string $token): ReplayShare
    {
        return ReplayShare::query()
            ->with('replay')
            ->where('token', $token)
            ->firstOrFail();
    }

    private function expiredShareResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'This replay share token has expired.',
        ], 403);
    }

    private function recordShareAccess(ReplayShare $share): void
    {
        $alreadyRecorded = $share->accessEvents()
            ->where('occurred_at', '>=', now()->subMinutes(self::SHARE_ACCESS_DEDUPE_MINUTES))
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $share->increment('access_count');
        $this->recordReplayAccess($share->replay, $share);
    }

    private function recordReplayAccess(Replay $replay, ?ReplayShare $share = null): void
    {
        $replay->accessEvents()->create([
            'replay_share_id' => $share?->id,
            'occurred_at' => now(),
        ]);
    }
}
