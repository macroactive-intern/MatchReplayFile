<?php

use App\Services\ReplayStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('stores replay files on the private replay disk under a user directory', function () {
    Storage::fake(ReplayStorage::DISK);

    $path = app(ReplayStorage::class)->store(
        UploadedFile::fake()->create('player-submitted-name.exe', 128, 'application/octet-stream'),
        42,
    );

    expect($path)
        ->toStartWith('replays/42/')
        ->toEndWith('.replay');

    $filename = basename($path, '.replay');

    expect(Str::isUuid($filename))->toBeTrue();

    Storage::disk(ReplayStorage::DISK)->assertExists($path);
});

it('configures replay storage as a local private disk without public urls', function () {
    $disk = config('filesystems.disks.'.ReplayStorage::DISK);

    expect($disk['driver'])->toBe('local')
        ->and($disk['root'])->toBe(storage_path('app/private'))
        ->and($disk['visibility'])->toBe('private')
        ->and($disk)->not->toHaveKey('url')
        ->and($disk)->not->toHaveKey('serve');

    expect(config('filesystems.links'))->not->toContain(storage_path('app/private'));
});
