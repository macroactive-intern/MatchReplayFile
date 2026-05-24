<?php

namespace App\Http\Controllers;

use App\Models\Replay;
use App\Models\ReplayShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ReplayDownloadController extends Controller
{
    public function forReplay(Replay $replay): JsonResponse
    {
        Gate::authorize('download', $replay);

        return $this->signedReplayDownloadResponse($replay);
    }

    public function forShare(string $token): JsonResponse
    {
        $share = ReplayShare::query()
            ->with('replay')
            ->where('token', $token)
            ->firstOrFail();

        if ($share->expires_at->isPast()) {
            return response()->json([
                'message' => 'This replay share token has expired.',
            ], 403);
        }

        $expiresAt = now()->addMinutes(10);

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'api.replay-shares.download.signed',
                $expiresAt,
                ['share' => $share],
            ),
            'expires_at' => $expiresAt,
        ]);
    }

    public function downloadReplay(Replay $replay)
    {
        return Storage::disk('local')->download(
            $replay->stored_path,
            $replay->original_filename,
        );
    }

    public function downloadShare(ReplayShare $share)
    {
        if ($share->expires_at->isPast()) {
            return response()->json([
                'message' => 'This replay share token has expired.',
            ], 403);
        }

        $share->increment('access_count');

        return Storage::disk('local')->download(
            $share->replay->stored_path,
            $share->replay->original_filename,
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
}
