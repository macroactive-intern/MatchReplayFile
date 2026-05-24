<?php

use App\Jobs\ProcessReplayMetadata;
use App\Models\Replay;
use App\Models\User;
use App\Services\ReplayStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('reads replay metadata and marks the replay ready', function () {
    Storage::fake(ReplayStorage::DISK);

    $payload = replayPayload(durationSeconds: 3661, playerCount: 12);
    $replay = replayWithStoredPath('replays/1/valid.replay');

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, $payload);

    (new ProcessReplayMetadata($replay))->handle();

    $replay->refresh();

    expect($replay->sha256_hash)->toBe(hash('sha256', $payload))
        ->and($replay->duration_seconds)->toBe(3661)
        ->and($replay->player_count)->toBe(12)
        ->and($replay->status)->toBe(Replay::STATUS_READY);
});

it('marks replay processing as failed when magic bytes are invalid', function () {
    Storage::fake(ReplayStorage::DISK);

    $replay = replayWithStoredPath('replays/1/invalid.replay');

    Storage::disk(ReplayStorage::DISK)->put(
        $replay->stored_path,
        'NOPE'.pack('Nn', 90, 2).str_repeat("\0", 6),
    );

    (new ProcessReplayMetadata($replay))->handle();

    expect($replay->refresh()->status)->toBe(Replay::STATUS_FAILED)
        ->and($replay->sha256_hash)->toBeNull()
        ->and($replay->duration_seconds)->toBeNull()
        ->and($replay->player_count)->toBeNull();
});

it('marks replay processing as failed when the file is missing without throwing', function () {
    Storage::fake(ReplayStorage::DISK);

    $replay = replayWithStoredPath('replays/1/missing.replay');

    (new ProcessReplayMetadata($replay))->handle();

    expect($replay->refresh()->status)->toBe(Replay::STATUS_FAILED);
});

it('retries the metadata job twice', function () {
    $replay = replayWithStoredPath('replays/1/retry.replay');

    expect((new ProcessReplayMetadata($replay))->tries)->toBe(2);
});

function replayPayload(int $durationSeconds, int $playerCount): string
{
    return 'REPQ'.pack('Nn', $durationSeconds, $playerCount).str_repeat("\0", 6).'body';
}

function replayWithStoredPath(string $storedPath): Replay
{
    $user = User::factory()->create();

    return Replay::create([
        'user_id' => $user->id,
        'title' => 'Metadata Test',
        'game_version' => '1.2.3',
        'original_filename' => 'metadata.replay',
        'stored_path' => $storedPath,
        'file_size' => 24,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_UPLOADED,
    ]);
}
