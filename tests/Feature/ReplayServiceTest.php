<?php

use App\Jobs\ProcessReplayMetadata;
use App\Models\Replay;
use App\Models\User;
use App\Services\ReplayService;
use App\Services\ReplayStorage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('stores an uploaded replay securely and creates a replay record', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent(
        'client-name.replay',
        'REPQ'.random_bytes(64),
    );

    $result = app(ReplayService::class)->uploadReplay($user, [
        'file' => $file,
        'title' => 'Ranked Final',
        'game_version' => '1.2.3',
        'guild_id' => null,
    ]);

    $replay = $result->replay;

    expect($result->duplicate)->toBeFalse();

    expect($replay)->toBeInstanceOf(Replay::class)
        ->and($replay->user_id)->toBe($user->id)
        ->and($replay->title)->toBe('Ranked Final')
        ->and($replay->game_version)->toBe('1.2.3')
        ->and($replay->original_filename)->toBe('client-name.replay')
        ->and($replay->sha256_hash)->toBe(hash('sha256', $file->getContent()))
        ->and($replay->status)->toBe(Replay::STATUS_UPLOADED)
        ->and($replay->stored_path)->toStartWith("replays/{$user->id}/")
        ->and($replay->stored_path)->toEndWith('.replay');

    expect(Str::isUuid(basename($replay->stored_path, '.replay')))->toBeTrue()
        ->and($replay->stored_path)->not->toStartWith(storage_path());

    Storage::disk(ReplayStorage::DISK)->assertExists($replay->stored_path);

    $this->assertDatabaseHas('replays', [
        'id' => $replay->id,
        'stored_path' => $replay->stored_path,
        'status' => Replay::STATUS_UPLOADED,
    ]);

    Bus::assertDispatched(ProcessReplayMetadata::class, function (ProcessReplayMetadata $job) use ($replay) {
        return $job->replay->is($replay);
    });
});

it('deletes uploaded files when an ignored insert is not a duplicate', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent(
        'client-name.replay',
        'REPQ'.random_bytes(64),
    );

    expect(fn () => app(ReplayService::class)->uploadReplay($user, [
        'file' => $file,
        'title' => null,
        'game_version' => '1.2.3',
        'guild_id' => null,
    ]))->toThrow(\RuntimeException::class, 'Unable to create or find replay upload record.');

    expect(Storage::disk(ReplayStorage::DISK)->allFiles("replays/{$user->id}"))->toBeEmpty();

    Bus::assertNotDispatched(ProcessReplayMetadata::class);
});

it('enforces unique stored replay paths', function () {
    $user = User::factory()->create();
    $storedPath = "replays/{$user->id}/collision.replay";

    Replay::create([
        'user_id' => $user->id,
        'title' => 'Original Path',
        'game_version' => '1.2.3',
        'original_filename' => 'original.replay',
        'stored_path' => $storedPath,
        'file_size' => 128,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_UPLOADED,
    ]);

    expect(fn () => Replay::create([
        'user_id' => $user->id,
        'title' => 'Duplicate Path',
        'game_version' => '1.2.3',
        'original_filename' => 'duplicate.replay',
        'stored_path' => $storedPath,
        'file_size' => 128,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_UPLOADED,
    ]))->toThrow(QueryException::class);
});
