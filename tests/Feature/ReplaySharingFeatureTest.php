<?php

use App\Models\Guild;
use App\Models\Replay;
use App\Models\ReplayShare;
use App\Models\User;
use App\Services\ReplayStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('allows guild members to access guild replays', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $guild = Guild::create(['name' => 'Guild Access']);
    $replay = replaySharingFeatureReplay($owner, $guild);

    $member->guilds()->attach($guild);

    $this->actingAs($member)
        ->getJson("/api/replays/{$replay->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $replay->id)
        ->assertJsonPath('data.guild_id', $guild->id);
});

it('prevents non members from accessing guild replays', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $guild = Guild::create(['name' => 'Private Guild Access']);
    $replay = replaySharingFeatureReplay($owner, $guild);

    $this->actingAs($user)
        ->getJson("/api/replays/{$replay->id}")
        ->assertForbidden();
});

it('rejects expired share tokens', function () {
    $owner = User::factory()->create();
    $replay = replaySharingFeatureReplay($owner);
    $share = replaySharingFeatureShare($replay, $owner, now()->subMinute());

    $this->getJson("/api/replays/shared/{$share->token}")
        ->assertForbidden()
        ->assertJsonPath('message', 'This replay share token has expired.');
});

it('does not increment access count when share tokens are resolved', function () {
    $owner = User::factory()->create();
    $replay = replaySharingFeatureReplay($owner);
    $share = replaySharingFeatureShare($replay, $owner);

    $this->getJson("/api/replays/shared/{$share->token}")
        ->assertOk()
        ->assertJsonPath('data.id', $replay->id);

    expect($share->refresh()->access_count)->toBe(0);
});

it('expires temporary signed replay download urls correctly', function () {
    Storage::fake(ReplayStorage::DISK);

    $owner = User::factory()->create();
    $replay = replaySharingFeatureReplay($owner);

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, replaySharingFeaturePayload());

    $url = URL::temporarySignedRoute(
        'api.replays.download.signed',
        now()->addMinutes(10),
        ['replay' => $replay],
    );

    $this->travel(11)->minutes();

    $this->getJson(replaySharingFeatureSignedPath($url))
        ->assertForbidden()
        ->assertJsonPath('message', 'Invalid or expired download signature.');
});

it('removes the replay file from disk when the replay is deleted', function () {
    Storage::fake(ReplayStorage::DISK);

    $owner = User::factory()->create();
    $replay = replaySharingFeatureReplay($owner);

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, replaySharingFeaturePayload());
    Storage::disk(ReplayStorage::DISK)->assertExists($replay->stored_path);

    $replay->delete();

    Storage::disk(ReplayStorage::DISK)->assertMissing($replay->stored_path);
});

function replaySharingFeatureReplay(User $owner, ?Guild $guild = null): Replay
{
    return Replay::create([
        'user_id' => $owner->id,
        'guild_id' => $guild?->id,
        'title' => 'Sharing Feature Replay',
        'game_version' => '1.2.3',
        'original_filename' => 'sharing-feature.replay',
        'stored_path' => "replays/{$owner->id}/sharing-feature-".uniqid().'.replay',
        'file_size' => 128,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_READY,
    ]);
}

function replaySharingFeatureShare(Replay $replay, User $owner, mixed $expiresAt = null): ReplayShare
{
    return $replay->shares()->create([
        'shared_by' => $owner->id,
        'scope' => ReplayShare::SCOPE_LINK,
        'token' => (string) Str::uuid(),
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);
}

function replaySharingFeaturePayload(): string
{
    return 'REPQ'.pack('Nn', 300, 2).str_repeat("\0", 6).'body';
}

function replaySharingFeatureSignedPath(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    return $path.'?'.$query;
}
