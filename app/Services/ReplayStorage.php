<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReplayStorage
{
    public const DISK = 'replays';

    public function store(UploadedFile $file, int|string $userId): string
    {
        return $file->storeAs(
            $this->directoryFor($userId),
            $this->filename(),
            self::DISK,
        );
    }

    public function directoryFor(int|string $userId): string
    {
        return 'replays/'.(int) $userId;
    }

    public function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    private function filename(): string
    {
        return Str::uuid().'.replay';
    }
}
