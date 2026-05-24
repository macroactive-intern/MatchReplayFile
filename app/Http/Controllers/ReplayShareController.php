<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReplayShareRequest;
use App\Models\Replay;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ReplayShareController extends Controller
{
    public function store(StoreReplayShareRequest $request, Replay $replay): JsonResponse
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
}
