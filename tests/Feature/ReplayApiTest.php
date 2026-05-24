<?php

use App\Jobs\ProcessReplayMetadata;
use App\Models\Guild;
use App\Models\Replay;
use App\Models\User;
use App\Services\ReplayStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('uploads replay files and dispatches metadata processing', function () {
    Bus::fake();
    Storage::fake(ReplayStorage::DISK);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/api/replays', [
            'file' => UploadedFile::fake()->createWithContent(
                'ranked.replay',
                replayApiPayload(),
            ),
            'title' => 'Ranked Replay',
            'game_version' => '1.2.3',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Ranked Replay')
        ->assertJsonPath('data.game_version', '1.2.3')
        ->assertJsonPath('data.status', Replay::STATUS_UPLOADED);

    $replay = Replay::findOrFail($response->json('data.id'));

    Storage::disk(ReplayStorage::DISK)->assertExists($replay->stored_path);
    Bus::assertDispatched(ProcessReplayMetadata::class);
});

it('lists paginated replays filtered by status and game version', function () {
    $user = User::factory()->create();

    replayForApi($user, [
        'title' => 'Ready Replay',
        'game_version' => '1.2.3',
        'status' => Replay::STATUS_READY,
    ]);
    replayForApi($user, [
        'title' => 'Failed Replay',
        'game_version' => '1.2.3',
        'status' => Replay::STATUS_FAILED,
    ]);
    replayForApi($user, [
        'title' => 'Other Version',
        'game_version' => '2.0.0',
        'status' => Replay::STATUS_READY,
    ]);

    $this->actingAs($user)
        ->getJson('/api/replays?status=ready&game_version=1.2.3')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Ready Replay')
        ->assertJsonPath('meta.current_page', 1);
});

it('rejects invalid replay status filters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/replays?status=typo')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('allows guild members to see guild replays in the index', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $guild = Guild::create(['name' => 'Index Guild']);

    $member->guilds()->attach($guild);
    replayForApi($owner, [
        'title' => 'Guild Replay',
        'guild_id' => $guild->id,
    ]);

    $this->actingAs($member)
        ->getJson('/api/replays')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Guild Replay');
});

it('caps replay index pagination size', function () {
    $user = User::factory()->create();

    foreach (range(1, 105) as $index) {
        replayForApi($user, [
            'title' => "Replay {$index}",
        ]);
    }

    $this->actingAs($user)
        ->getJson('/api/replays?per_page=1000000')
        ->assertOk()
        ->assertJsonCount(100, 'data')
        ->assertJsonPath('meta.per_page', 100);
});

it('shows policy protected replay metadata', function () {
    $owner = User::factory()->create();
    $replay = replayForApi($owner);

    $this->actingAs($owner)
        ->getJson("/api/replays/{$replay->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $replay->id);
});

it('rejects replay show requests for unauthorized users', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $replay = replayForApi($owner);

    $this->actingAs($user)
        ->getJson("/api/replays/{$replay->id}")
        ->assertForbidden();
});

it('updates replay title and guild for owners only', function () {
    $owner = User::factory()->create();
    $guild = Guild::create(['name' => 'Update Guild']);
    $owner->guilds()->attach($guild);
    $replay = replayForApi($owner);

    $this->actingAs($owner)
        ->putJson("/api/replays/{$replay->id}", [
            'title' => 'Updated Title',
            'guild_id' => $guild->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonPath('data.guild_id', $guild->id);
});

it('rejects replay updates from non owners', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $replay = replayForApi($owner);

    $this->actingAs($user)
        ->putJson("/api/replays/{$replay->id}", [
            'title' => 'Not Allowed',
        ])
        ->assertForbidden();
});

it('deletes replay records for owners', function () {
    Storage::fake(ReplayStorage::DISK);

    $owner = User::factory()->create();
    $replay = replayForApi($owner);

    Storage::disk(ReplayStorage::DISK)->put($replay->stored_path, replayApiPayload());

    $this->actingAs($owner)
        ->deleteJson("/api/replays/{$replay->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('replays', [
        'id' => $replay->id,
    ]);
    Storage::disk(ReplayStorage::DISK)->assertMissing($replay->stored_path);
});

it('rejects replay deletion from non owners', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $replay = replayForApi($owner);

    $this->actingAs($user)
        ->deleteJson("/api/replays/{$replay->id}")
        ->assertForbidden();
});

function replayForApi(User $owner, array $overrides = []): Replay
{
    return Replay::create(array_merge([
        'user_id' => $owner->id,
        'title' => 'API Replay',
        'game_version' => '1.2.3',
        'original_filename' => 'api.replay',
        'stored_path' => "replays/{$owner->id}/api-".uniqid().'.replay',
        'file_size' => 128,
        'mime_type' => 'application/octet-stream',
        'status' => Replay::STATUS_READY,
    ], $overrides));
}

function replayApiPayload(): string
{
    return 'REPQ'.pack('Nn', 300, 2).str_repeat("\0", 6).'body';
}
