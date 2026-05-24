<?php

use App\Jobs\ProcessReplayMetadata;
use App\Models\Replay;
use App\Models\User;
use App\Services\ReplayStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('uploads a valid replay through the API', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload())
        ->assertCreated()
        ->assertJsonPath('data.title', 'Upload Feature Replay')
        ->assertJsonPath('data.game_version', '1.2.3')
        ->assertJsonPath('data.status', Replay::STATUS_UPLOADED);

    $this->assertDatabaseHas('replays', [
        'id' => $response->json('data.id'),
        'user_id' => $user->id,
        'status' => Replay::STATUS_UPLOADED,
    ]);
});

it('rejects replay uploads with invalid magic bytes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload([
            'file' => UploadedFile::fake()->createWithContent(
                'invalid.replay',
                'NOPE'.pack('Nn', 300, 2).str_repeat("\0", 6),
            ),
        ]))
        ->assertSessionHasErrors('file');
});

it('rejects replay uploads larger than 25 megabytes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload([
            'file' => UploadedFile::fake()->create(
                'too-large.replay',
                (25 * 1024) + 1,
                'application/octet-stream',
            ),
        ]))
        ->assertSessionHasErrors('file');
});

it('stores replay uploads under the user replay directory', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload())
        ->assertCreated();

    $replay = Replay::findOrFail($response->json('data.id'));

    expect($replay->stored_path)->toStartWith("replays/{$user->id}/");

    Storage::disk(ReplayStorage::DISK)->assertExists($replay->stored_path);
});

it('uses a uuid replay filename for stored uploads', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload())
        ->assertCreated();

    $replay = Replay::findOrFail($response->json('data.id'));

    expect($replay->stored_path)->toEndWith('.replay')
        ->and(Str::isUuid(basename($replay->stored_path, '.replay')))->toBeTrue();
});

it('dispatches the replay metadata processing job after upload', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload())
        ->assertCreated();

    Bus::assertDispatched(ProcessReplayMetadata::class);
});

it('returns the existing replay info when the same user uploads a duplicate file', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();
    $contents = replayUploadBinary(durationSeconds: 300, playerCount: 2);

    $firstResponse = $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload([
            'file' => replayUploadFileWithContents('original.replay', $contents),
            'title' => 'Original Replay',
        ]))
        ->assertCreated()
        ->assertJsonPath('meta.duplicate', false);

    $firstReplay = Replay::findOrFail($firstResponse->json('data.id'));

    $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload([
            'file' => replayUploadFileWithContents('renamed-copy.replay', $contents),
            'title' => 'Duplicate Attempt',
        ]))
        ->assertOk()
        ->assertJsonPath('data.id', $firstReplay->id)
        ->assertJsonPath('data.title', 'Original Replay')
        ->assertJsonPath('meta.duplicate', true);

    expect(Replay::where('user_id', $user->id)->count())->toBe(1)
        ->and(Storage::disk(ReplayStorage::DISK)->allFiles("replays/{$user->id}"))->toHaveCount(1);

    Bus::assertDispatched(ProcessReplayMetadata::class, 1);
});

it('allows different users to upload matching replay files', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $contents = replayUploadBinary(durationSeconds: 300, playerCount: 2);

    $this->actingAs($firstUser)
        ->post('/api/replays', replayUploadPayload([
            'file' => replayUploadFileWithContents('first.replay', $contents),
        ]))
        ->assertCreated()
        ->assertJsonPath('meta.duplicate', false);

    $this->actingAs($secondUser)
        ->post('/api/replays', replayUploadPayload([
            'file' => replayUploadFileWithContents('second.replay', $contents),
        ]))
        ->assertCreated()
        ->assertJsonPath('meta.duplicate', false);

    expect(Replay::count())->toBe(2);

    Bus::assertDispatched(ProcessReplayMetadata::class, 2);
});

it('updates replay status to ready after metadata processing succeeds', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload([
            'file' => replayUploadFile(durationSeconds: 900, playerCount: 4),
        ]))
        ->assertCreated();

    $replay = Replay::findOrFail($response->json('data.id'));

    (new ProcessReplayMetadata($replay))->handle();

    expect($replay->refresh()->status)->toBe(Replay::STATUS_READY)
        ->and($replay->duration_seconds)->toBe(900)
        ->and($replay->player_count)->toBe(4)
        ->and($replay->sha256_hash)->not->toBeNull();
});

it('sets replay status to failed when metadata parsing fails', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/api/replays', replayUploadPayload())
        ->assertCreated();

    $replay = Replay::findOrFail($response->json('data.id'));

    Storage::disk(ReplayStorage::DISK)->put(
        $replay->stored_path,
        'NOPE'.pack('Nn', 300, 2).str_repeat("\0", 6),
    );

    (new ProcessReplayMetadata($replay))->handle();

    expect($replay->refresh()->status)->toBe(Replay::STATUS_FAILED);
});

function replayUploadPayload(array $overrides = []): array
{
    return array_merge([
        'file' => replayUploadFile(),
        'title' => 'Upload Feature Replay',
        'game_version' => '1.2.3',
    ], $overrides);
}

function replayUploadFile(int $durationSeconds = 300, int $playerCount = 2): UploadedFile
{
    return replayUploadFileWithContents(
        'client-name.replay',
        replayUploadBinary($durationSeconds, $playerCount),
    );
}

function replayUploadFileWithContents(string $name, string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

function replayUploadBinary(int $durationSeconds = 300, int $playerCount = 2): string
{
    return 'REPQ'.pack('Nn', $durationSeconds, $playerCount).str_repeat("\0", 6).'body';
}
