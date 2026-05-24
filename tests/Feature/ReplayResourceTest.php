<?php

use App\Http\Resources\ReplayResource;
use App\Models\Replay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

it('serializes replay summary fields', function () {
    $createdAt = CarbonImmutable::parse('2026-05-24 12:34:56');

    $replay = new Replay([
        'title' => 'Opening Match',
        'game_version' => '1.2.3',
        'status' => Replay::STATUS_READY,
        'duration_seconds' => 742,
        'player_count' => 8,
        'file_size' => 153600,
        'guild_id' => 11,
    ]);

    $replay->id = 7;
    $replay->created_at = $createdAt;

    $serialized = (new ReplayResource($replay))->toArray(Request::create('/'));

    expect($serialized)->toMatchArray([
        'id' => 7,
        'title' => 'Opening Match',
        'game_version' => '1.2.3',
        'status' => Replay::STATUS_READY,
        'duration_seconds' => 742,
        'player_count' => 8,
        'file_size' => 153600,
        'guild_id' => 11,
    ])
        ->and($serialized['created_at']->equalTo($createdAt))->toBeTrue();
});
