<?php

use App\Http\Requests\StoreReplayRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::post('/test/replays', fn (StoreReplayRequest $request) => response()->noContent());
});

it('accepts valid replay uploads for guilds the user belongs to', function () {
    $user = User::factory()->create();
    $guild = Guild::create(['name' => 'Raid Team']);

    $user->guilds()->attach($guild);

    $this->actingAs($user)
        ->post('/test/replays', [
            'file' => replayFile(),
            'title' => 'Final Match',
            'game_version' => '1.2.3',
            'guild_id' => $guild->id,
        ])
        ->assertNoContent();
});

it('rejects replay uploads with invalid magic bytes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/test/replays', [
            'file' => UploadedFile::fake()->createWithContent('match.replay', random_bytes(64)),
            'title' => 'Final Match',
            'game_version' => '1.2.3',
        ])
        ->assertSessionHasErrors('file');
});

it('rejects guild ids the authenticated user does not belong to', function () {
    $user = User::factory()->create();
    $guild = Guild::create(['name' => 'Other Team']);

    $this->actingAs($user)
        ->post('/test/replays', [
            'file' => replayFile(),
            'title' => 'Final Match',
            'game_version' => '1.2.3',
            'guild_id' => $guild->id,
        ])
        ->assertSessionHasErrors('guild_id');
});

it('requires semantic game versions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/test/replays', [
            'file' => replayFile(),
            'title' => 'Final Match',
            'game_version' => '1.2',
        ])
        ->assertSessionHasErrors('game_version');
});

function replayFile(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'spoofed-client-name.replay',
        'REPQ'.random_bytes(64),
    );
}
