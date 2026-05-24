<?php

use App\Models\Guild;
use App\Models\Replay;
use App\Models\ReplayShare;
use App\Models\User;
use App\Services\ReplayStorage;
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

    expect(now()->diffInSeconds($response->json('expires_at'), false))->toBeBetween(590, 600)
        ->and($response->json('url'))->not->toContain($replay->stored_path);
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
    Storage::fake(ReplayStorage::DISK);

    $owner = User::factory()->create();
    $replay = replayForDownload($owner);

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, 'REPQ'.str_repeat("\0", 12));

    $signedUrl = $this->actingAs($owner)
        ->getJson("/api/replays/{$replay->id}/download")
        ->json('url');

    $this->get(signedPath($signedUrl))
        ->assertOk()
        ->assertHeader('content-disposition');

    $this->get(signedPath($signedUrl))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=download.replay')
        ->assertHeaderMissing('x-accel-redirect');
});

it('returns forbidden for invalid replay download signatures', function () {
    Storage::fake(ReplayStorage::DISK);

    $owner = User::factory()->create();
    $replay = replayForDownload($owner);

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, 'REPQ'.str_repeat("\0", 12));

    $signedUrl = $this->actingAs($owner)
        ->getJson("/api/replays/{$replay->id}/download")
        ->json('url');

    $this->getJson(tamperedSignedPath($signedUrl))
        ->assertForbidden()
        ->assertJsonPath('message', 'Invalid or expired download signature.');
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

    expect(now()->diffInSeconds($response->json('expires_at'), false))->toBeBetween(590, 600)
        ->and($response->json('url'))->not->toContain($replay->stored_path);
});

it('limits shared signed download urls to the share expiry when it expires sooner', function () {
    $owner = User::factory()->create();
    $replay = replayForDownload($owner);
    $share = replayDownloadShare($replay, $owner, expiresAt: now()->addMinutes(2));

    $response = $this->getJson("/api/replays/shared/{$share->token}/download")
        ->assertOk()
        ->assertJsonStructure([
            'url',
            'expires_at',
        ]);

    assertTemporarySignedUrl($response->json('url'), minSeconds: 110, maxSeconds: 120);

    expect(now()->diffInSeconds($response->json('expires_at'), false))->toBeBetween(110, 120);
});

it('increments share access count when a signed shared download url is used', function () {
    Storage::fake(ReplayStorage::DISK);

    $owner = User::factory()->create();
    $replay = replayForDownload($owner);
    $share = replayDownloadShare($replay, $owner);

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, 'REPQ'.str_repeat("\0", 12));

    $signedUrl = $this->getJson("/api/replays/shared/{$share->token}/download")
        ->json('url');

    expect($share->refresh()->access_count)->toBe(0);

    $this->get(signedPath($signedUrl))->assertOk();

    expect($share->refresh()->access_count)->toBe(1);
});

it('does not double count a shared metadata view followed by a signed shared download', function () {
    Storage::fake(ReplayStorage::DISK);

    $owner = User::factory()->create();
    $replay = replayForDownload($owner);
    $share = replayDownloadShare($replay, $owner);

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, 'REPQ'.str_repeat("\0", 12));

    $this->getJson("/api/replays/shared/{$share->token}")
        ->assertOk();

    expect($share->refresh()->access_count)->toBe(1);
    $this->assertDatabaseCount('replay_access_events', 1);

    $signedUrl = $this->getJson("/api/replays/shared/{$share->token}/download")
        ->json('url');

    $this->get(signedPath($signedUrl))->assertOk();

    expect($share->refresh()->access_count)->toBe(1);
    $this->assertDatabaseCount('replay_access_events', 1);
});

it('returns forbidden for invalid shared download signatures', function () {
    Storage::fake(ReplayStorage::DISK);

    $owner = User::factory()->create();
    $replay = replayForDownload($owner);
    $share = replayDownloadShare($replay, $owner);

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, 'REPQ'.str_repeat("\0", 12));

    $signedUrl = $this->getJson("/api/replays/shared/{$share->token}/download")
        ->json('url');

    $this->getJson(tamperedSignedPath($signedUrl))
        ->assertForbidden()
        ->assertJsonPath('message', 'Invalid or expired download signature.');

    expect($share->refresh()->access_count)->toBe(0);
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

function assertTemporarySignedUrl(string $url, int $minSeconds = 590, int $maxSeconds = 600): void
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toHaveKeys(['expires', 'signature'])
        ->and((int) $query['expires'] - now()->timestamp)->toBeBetween($minSeconds, $maxSeconds);
}

function signedPath(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    return $path.'?'.$query;
}

function tamperedSignedPath(string $url): string
{
    return signedPath($url).'tampered';
}
