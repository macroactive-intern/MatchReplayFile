<?php

use App\Http\Requests\UpdateReplayRequest;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::patch('/test/replays/{replay}', fn (UpdateReplayRequest $request) => response()->noContent());
});

it('allows updating replay title and guild id for a guild the user belongs to', function () {
    $user = User::factory()->create();
    $guild = Guild::create(['name' => 'Replay Reviewers']);

    $user->guilds()->attach($guild);

    $this->actingAs($user)
        ->patch('/test/replays/1', [
            'title' => 'Updated Replay Title',
            'guild_id' => $guild->id,
        ])
        ->assertNoContent();
});

it('allows clearing replay guild id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/test/replays/1', [
            'guild_id' => null,
        ])
        ->assertNoContent();
});

it('rejects replay titles over 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/test/replays/1', [
            'title' => str_repeat('a', 256),
        ])
        ->assertSessionHasErrors('title');
});

it('rejects guild ids the authenticated user does not belong to', function () {
    $user = User::factory()->create();
    $guild = Guild::create(['name' => 'Other Guild']);

    $this->actingAs($user)
        ->patch('/test/replays/1', [
            'guild_id' => $guild->id,
        ])
        ->assertSessionHasErrors('guild_id');
});
