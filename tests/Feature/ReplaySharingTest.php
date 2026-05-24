<?php

use App\Models\Guild;
use App\Models\Replay;
use App\Models\ReplayShare;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates a replay share token with an expiry date', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-24 12:00:00'));

    $owner = User::factory()->create();
    $replay = replayForSharing($owner);

    $response = $this->actingAs($owner)
        ->postJson("/api/replays/{$replay->id}/share", [
            'scope' => ReplayShare::SCOPE_LINK,
            'expiry_hours' => 24,
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'token',
            'expires_at',
        ]);

    expect(Str::isUuid($response->json('token')))->toBeTrue()
        ->and(CarbonImmutable::parse($response->json('expires_at'))->equalTo(now()->addHours(24)))->toBeTrue();

    $this->assertDatabaseHas('replay_shares', [
        'replay_id' => $replay->id,
        'shared_by' => $owner->id,
        'scope' => ReplayShare::SCOPE_LINK,
        'token' => $response->json('token'),
        'access_count' => 0,
    ]);

    CarbonImmutable::setTestNow();
});

it('allows guild scoped replay shares', function () {
    $owner = User::factory()->create();
    $guild = Guild::create(['name' => 'Share Guild']);
    $replay = replayForSharing($owner, $guild);

    $this->actingAs($owner)
        ->postJson("/api/replays/{$replay->id}/share", [
            'scope' => ReplayShare::SCOPE_GUILD,
            'expiry_hours' => 1,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('replay_shares', [
        'replay_id' => $replay->id,
        'scope' => ReplayShare::SCOPE_GUILD,
    ]);
});

it('rejects invalid share scope and expiry values', function (array $payload, string $field) {
    $owner = User::factory()->create();
    $replay = replayForSharing($owner);

    $this->actingAs($owner)
        ->postJson("/api/replays/{$replay->id}/share", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'invalid scope' => [
        [
            'scope' => 'public',
            'expiry_hours' => 24,
        ],
        'scope',
    ],
    'zero expiry' => [
        [
            'scope' => ReplayShare::SCOPE_LINK,
            'expiry_hours' => 0,
        ],
        'expiry_hours',
    ],
    'too long expiry' => [
        [
            'scope' => ReplayShare::SCOPE_LINK,
            'expiry_hours' => 169,
        ],
        'expiry_hours',
    ],
]);

it('rejects sharing by non owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $guild = Guild::create(['name' => 'Viewer Guild']);
    $replay = replayForSharing($owner, $guild);

    $member->guilds()->attach($guild);

    $this->actingAs($member)
        ->postJson("/api/replays/{$replay->id}/share", [
            'scope' => ReplayShare::SCOPE_LINK,
            'expiry_hours' => 24,
        ])
        ->assertForbidden();
});

it('returns replay metadata for a valid share token without incrementing access count', function () {
    $owner = User::factory()->create();
    $replay = replayForSharing($owner);
    $share = $replay->shares()->create([
        'shared_by' => $owner->id,
        'scope' => ReplayShare::SCOPE_LINK,
        'token' => (string) Str::uuid(),
        'expires_at' => now()->addHour(),
    ]);

    $this->getJson("/api/replays/shared/{$share->token}")
        ->assertOk()
        ->assertJsonPath('data.id', $replay->id)
        ->assertJsonPath('data.title', 'Shared Replay')
        ->assertJsonPath('data.game_version', '1.2.3')
        ->assertJsonPath('data.status', Replay::STATUS_READY)
        ->assertJsonPath('data.file_size', 128)
        ->assertJsonPath('data.guild_id', null);

    expect($share->refresh()->access_count)->toBe(0);
});

it('returns not found for unknown share tokens', function () {
    $this->getJson('/api/replays/shared/not-a-real-token')
        ->assertNotFound();
});

it('returns forbidden with a clear message for expired share tokens', function () {
    $owner = User::factory()->create();
    $replay = replayForSharing($owner);
    $share = $replay->shares()->create([
        'shared_by' => $owner->id,
        'scope' => ReplayShare::SCOPE_LINK,
        'token' => (string) Str::uuid(),
        'expires_at' => now()->subMinute(),
    ]);

    $this->getJson("/api/replays/shared/{$share->token}")
        ->assertForbidden()
        ->assertJsonPath('message', 'This replay share token has expired.');

    expect($share->refresh()->access_count)->toBe(0);
});

function replayForSharing(User $owner, ?Guild $guild = null): Replay
{
    return Replay::create([
        'user_id' => $owner->id,
        'guild_id' => $guild?->id,
        'title' => 'Shared Replay',
        'game_version' => '1.2.3',
        'original_filename' => 'shared.replay',
        'stored_path' => "replays/{$owner->id}/shared.replay",
        'file_size' => 128,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_READY,
    ]);
}
