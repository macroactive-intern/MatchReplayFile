<?php

use App\Models\Replay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('deletes the replay file from local storage when a replay is deleted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $path = "replays/{$user->id}/deleted.replay";

    Storage::disk('local')->put($path, 'REPQ'.str_repeat("\0", 12));

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

    Storage::disk('local')->assertExists($path);

    $replay->delete();

    Storage::disk('local')->assertMissing($path);
});
