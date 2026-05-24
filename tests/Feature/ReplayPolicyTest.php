<?php

use App\Models\Guild;
use App\Models\Replay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('allows replay owners to view download update and delete', function () {
    $owner = User::factory()->create();
    $replay = replayPolicyReplay($owner);

    expect(Gate::forUser($owner)->allows('view', $replay))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('download', $replay))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('viewAnalytics', $replay))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $replay))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $replay))->toBeTrue();
});

it('allows guild members to view and download guild replays', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $guild = Guild::create(['name' => 'Analysts']);
    $replay = replayPolicyReplay($owner, $guild);

    $member->guilds()->attach($guild);

    expect(Gate::forUser($member)->allows('view', $replay))->toBeTrue()
        ->and(Gate::forUser($member)->allows('download', $replay))->toBeTrue()
        ->and(Gate::forUser($member)->denies('viewAnalytics', $replay))->toBeTrue()
        ->and(Gate::forUser($member)->denies('update', $replay))->toBeTrue()
        ->and(Gate::forUser($member)->denies('delete', $replay))->toBeTrue();
});

it('denies access to users who neither own nor share the replay guild', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $guild = Guild::create(['name' => 'Private Guild']);
    $replay = replayPolicyReplay($owner, $guild);

    expect(Gate::forUser($user)->denies('view', $replay))->toBeTrue()
        ->and(Gate::forUser($user)->denies('download', $replay))->toBeTrue()
        ->and(Gate::forUser($user)->denies('viewAnalytics', $replay))->toBeTrue()
        ->and(Gate::forUser($user)->denies('update', $replay))->toBeTrue()
        ->and(Gate::forUser($user)->denies('delete', $replay))->toBeTrue();
});

it('caches guild membership lookups during replay policy checks', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $guild = Guild::create(['name' => 'Cached Guild']);
    $replay = replayPolicyReplay($owner, $guild);

    $member->guilds()->attach($guild);

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect(Gate::forUser($member)->allows('view', $replay))->toBeTrue();

    $guildQueriesAfterFirstCheck = replayPolicyGuildQueryCount();

    expect(Gate::forUser($member)->allows('download', $replay))->toBeTrue()
        ->and($guildQueriesAfterFirstCheck)->toBe(1)
        ->and(replayPolicyGuildQueryCount())->toBe(1);

    DB::disableQueryLog();
});

function replayPolicyReplay(User $owner, ?Guild $guild = null): Replay
{
    return Replay::create([
        'user_id' => $owner->id,
        'guild_id' => $guild?->id,
        'title' => 'Policy Replay',
        'game_version' => '1.2.3',
        'original_filename' => 'policy.replay',
        'stored_path' => "replays/{$owner->id}/policy.replay",
        'file_size' => 128,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_READY,
    ]);
}

function replayPolicyGuildQueryCount(): int
{
    return collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'guild_user'))
        ->count();
}
