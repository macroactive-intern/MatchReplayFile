<?php

use App\Models\Guild;
use App\Models\Replay;
use App\Models\ReplayShare;
use App\Models\User;
use App\Services\ReplayStorage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns chart friendly replay access counts for the last thirty days', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 12:00:00'));

    $owner = User::factory()->create();
    $replay = replayAnalyticsReplay($owner);

    $replay->accessEvents()->createMany([
        ['occurred_at' => now()->subDays(30)],
        ['occurred_at' => now()->subDays(29)],
        ['occurred_at' => now()->subDays(2)],
        ['occurred_at' => now()->subDays(2)->addHour()],
        ['occurred_at' => now()],
    ]);

    $response = $this->actingAs($owner)
        ->getJson("/api/replays/{$replay->id}/analytics")
        ->assertOk()
        ->assertJsonPath('data.replay_id', $replay->id)
        ->assertJsonPath('data.from', '2026-04-26')
        ->assertJsonPath('data.to', '2026-05-25')
        ->assertJsonPath('data.total_access_count', 4)
        ->assertJsonPath('data.datasets.0.label', 'Accesses');

    $data = $response->json('data');

    expect($data['access_count_by_day'])->toHaveCount(30)
        ->and($data['labels'])->toHaveCount(30)
        ->and($data['datasets'][0]['data'])->toHaveCount(30)
        ->and($data['access_count_by_day'][0])->toBe([
            'date' => '2026-04-26',
            'count' => 1,
        ])
        ->and($data['access_count_by_day'][27])->toBe([
            'date' => '2026-05-23',
            'count' => 2,
        ])
        ->and($data['access_count_by_day'][29])->toBe([
            'date' => '2026-05-25',
            'count' => 1,
        ])
        ->and($data['labels'][27])->toBe('2026-05-23')
        ->and($data['datasets'][0]['data'][27])->toBe(2);

    CarbonImmutable::setTestNow();
});

it('limits replay analytics to the replay owner', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $replay = replayAnalyticsReplay($owner);

    $this->actingAs($user)
        ->getJson("/api/replays/{$replay->id}/analytics")
        ->assertForbidden();
});

it('does not expose replay analytics to guild members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $guild = Guild::create(['name' => 'Analytics Guild']);
    $replay = replayAnalyticsReplay($owner, $guild);

    $member->guilds()->attach($guild);

    $this->actingAs($member)
        ->getJson("/api/replays/{$replay->id}/analytics")
        ->assertForbidden();
});

it('deduplicates shared metadata and signed download access events', function () {
    Storage::fake(ReplayStorage::DISK);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 12:00:00'));

    $owner = User::factory()->create();
    $replay = replayAnalyticsReplay($owner);
    $share = replayAnalyticsShare($replay, $owner);

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, 'REPQ'.str_repeat("\0", 12));

    $this->getJson("/api/replays/shared/{$share->token}")
        ->assertOk();

    $this->assertDatabaseCount('replay_access_events', 1);
    $this->assertDatabaseHas('replay_access_events', [
        'replay_id' => $replay->id,
        'replay_share_id' => $share->id,
    ]);

    expect($share->refresh()->access_count)->toBe(1);

    $directUrl = URL::temporarySignedRoute(
        'api.replays.download.signed',
        now()->addMinutes(10),
        ['replay' => $replay],
    );

    $this->get(replayAnalyticsSignedPath($directUrl))
        ->assertOk();

    $sharedUrl = URL::temporarySignedRoute(
        'api.replay-shares.download.signed',
        now()->addMinutes(10),
        ['share' => $share],
    );

    $this->get(replayAnalyticsSignedPath($sharedUrl))
        ->assertOk();

    $this->assertDatabaseCount('replay_access_events', 2);
    $this->assertDatabaseHas('replay_access_events', [
        'replay_id' => $replay->id,
        'replay_share_id' => $share->id,
    ]);

    expect($share->refresh()->access_count)->toBe(1);

    CarbonImmutable::setTestNow();
});

function replayAnalyticsReplay(User $owner, ?Guild $guild = null): Replay
{
    return Replay::create([
        'user_id' => $owner->id,
        'guild_id' => $guild?->id,
        'title' => 'Analytics Replay',
        'game_version' => '1.2.3',
        'original_filename' => 'analytics.replay',
        'stored_path' => "replays/{$owner->id}/analytics-".uniqid().'.replay',
        'file_size' => 128,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_READY,
    ]);
}

function replayAnalyticsShare(Replay $replay, User $owner): ReplayShare
{
    return $replay->shares()->create([
        'shared_by' => $owner->id,
        'scope' => ReplayShare::SCOPE_LINK,
        'token' => (string) Str::uuid(),
        'expires_at' => now()->addHour(),
    ]);
}

function replayAnalyticsSignedPath(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    return $path.'?'.$query;
}
