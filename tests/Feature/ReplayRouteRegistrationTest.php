<?php

use App\Models\Replay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('protects replay management routes with sanctum authentication', function (string $method, string $uri) {
    $response = $this->{$method.'Json'}($uri);

    $response->assertUnauthorized();
})->with([
    'listing' => ['get', '/api/replays'],
    'uploads' => ['post', '/api/replays'],
    'show' => ['get', '/api/replays/1'],
    'updates' => ['put', '/api/replays/1'],
    'deletes' => ['delete', '/api/replays/1'],
    'direct downloads' => ['get', '/api/replays/1/download'],
    'analytics' => ['get', '/api/replays/1/analytics'],
    'share creation' => ['post', '/api/replays/1/share'],
]);

it('leaves shared replay routes public', function () {
    $this->getJson('/api/replays/shared/not-a-token')->assertNotFound();
    $this->getJson('/api/replays/shared/not-a-token/download')->assertNotFound();
});

it('rate limits public replay share routes', function (string $uri) {
    $route = Route::getRoutes()->match(Request::create($uri, 'GET'));

    expect($route->gatherMiddleware())->toContain('throttle:60,1');
})->with([
    'shared metadata' => ['/api/replays/shared/not-a-token'],
    'shared signed url' => ['/api/replays/shared/not-a-token/download'],
    'shared file serving' => ['/api/replay-shares/1/download-file'],
]);

it('allows sanctum authenticated users through replay routes', function () {
    $user = User::factory()->create();
    $replay = Replay::create([
        'user_id' => $user->id,
        'title' => 'Sanctum Replay',
        'game_version' => '1.2.3',
        'original_filename' => 'sanctum.replay',
        'stored_path' => "replays/{$user->id}/sanctum.replay",
        'file_size' => 128,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_READY,
    ]);

    $token = $user->createToken('tests')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/replays/{$replay->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $replay->id);
});
