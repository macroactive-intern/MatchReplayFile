<?php

use App\Models\Replay;
use App\Models\User;
use App\Services\ReplayStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('deletes the replay file from replay storage when a replay is deleted', function () {
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();
    $path = "replays/{$user->id}/deleted.replay";

    Storage::disk(ReplayStorage::DISK)->put($path, 'REPQ'.str_repeat("\0", 12));

    $replay = Replay::create([
        'user_id' => $user->id,
        'title' => 'Deleted Replay',
        'game_version' => '1.2.3',
        'original_filename' => 'deleted.replay',
        'stored_path' => $path,
        'file_size' => 16,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_READY,
    ]);

    Storage::disk(ReplayStorage::DISK)->assertExists($path);

    $replay->delete();

    Storage::disk(ReplayStorage::DISK)->assertMissing($path);
});
