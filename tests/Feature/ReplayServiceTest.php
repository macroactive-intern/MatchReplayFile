<?php

use App\Jobs\ProcessReplayMetadata;
use App\Models\Replay;
use App\Models\User;
use App\Services\ReplayService;
use App\Services\ReplayStorage;
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
