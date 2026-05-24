<?php

use App\Models\Guild;
use App\Models\Replay;
use App\Models\ReplayShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates a ten minute signed replay download url for authorized users', function () {
    $owner = User::factory()->create();
    $replay = replayForDownload($owner);

    $response = $this->actingAs($owner)
        ->getJson("/api/replays/{$replay->id}/download")
        ->assertOk()
        ->assertJsonStructure([
            'url',
            'expires_at',
        ]);

    assertTemporarySignedUrl($response->json('url'));

    expect(now()->diffInSeconds($response->json('expires_at'), false))->toBeBetween(590, 600);
});

it('allows guild members to request replay download urls', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $guild = Guild::create(['name' => 'Download Guild']);
    $replay = replayForDownload($owner, $guild);

    $member->guilds()->attach($guild);

    $this->actingAs($member)
        ->getJson("/api/replays/{$replay->id}/download")
        ->assertOk()
        ->assertJsonStructure(['url', 'expires_at']);
});

it('rejects replay download urls for unauthorized users', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $replay = replayForDownload($owner);

    $this->actingAs($user)
        ->getJson("/api/replays/{$replay->id}/download")
        ->assertForbidden();
});

it('streams replay files through signed download urls', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $replay = replayForDownload($owner);

    Storage::disk('local')->put($replay->stored_path, 'REPQ'.str_repeat("\0", 12));

    $signedUrl = $this->actingAs($owner)
        ->getJson("/api/replays/{$replay->id}/download")
        ->json('url');

    $this->get(signedPath($signedUrl))
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('creates a ten minute signed download url for valid share tokens', function () {
    $owner = User::factory()->create();
    $replay = replayForDownload($owner);
    $share = replayDownloadShare($replay, $owner);

    $response = $this->getJson("/api/replays/shared/{$share->token}/download")
        ->assertOk()
        ->assertJsonStructure([
            'url',
            'expires_at',
        ]);

    assertTemporarySignedUrl($response->json('url'));

    expect(now()->diffInSeconds($response->json('expires_at'), false))->toBeBetween(590, 600);
});

it('increments share access count when a signed shared download url is used', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $replay = replayForDownload($owner);
    $share = replayDownloadShare($replay, $owner);

    Storage::disk('local')->put($replay->stored_path, 'REPQ'.str_repeat("\0", 12));

    $signedUrl = $this->getJson("/api/replays/shared/{$share->token}/download")
        ->json('url');

    expect($share->refresh()->access_count)->toBe(0);

    $this->get(signedPath($signedUrl))->assertOk();

    expect($share->refresh()->access_count)->toBe(1);
});

it('rejects shared download urls for expired share tokens', function () {
    $owner = User::factory()->create();
    $replay = replayForDownload($owner);
    $share = replayDownloadShare($replay, $owner, expiresAt: now()->subMinute());

    $this->getJson("/api/replays/shared/{$share->token}/download")
        ->assertForbidden()
        ->assertJsonPath('message', 'This replay share token has expired.');
});

function replayForDownload(User $owner, ?Guild $guild = null): Replay
{
    return Replay::create([
        'user_id' => $owner->id,
        'guild_id' => $guild?->id,
        'title' => 'Download Replay',
        'game_version' => '1.2.3',
        'original_filename' => 'download.replay',
        'stored_path' => "replays/{$owner->id}/download.replay",
        'file_size' => 128,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_READY,
    ]);
}

function replayDownloadShare(Replay $replay, User $owner, mixed $expiresAt = null): ReplayShare
{
    return $replay->shares()->create([
        'shared_by' => $owner->id,
        'scope' => ReplayShare::SCOPE_LINK,
        'token' => (string) Str::uuid(),
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);
}

function assertTemporarySignedUrl(string $url): void
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toHaveKeys(['expires', 'signature'])
        ->and((int) $query['expires'] - now()->timestamp)->toBeBetween(590, 600);
}

function signedPath(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    return $path.'?'.$query;
}
