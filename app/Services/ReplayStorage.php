<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReplayStorage
{
    public const DISK = 'replays';

    public function store(UploadedFile $file, int|string $userId): string
    {
        return $this->storeAs(
            $file,
            $userId,
            $this->filename(),
        );
    }

    public function storeAs(UploadedFile $file, int|string $userId, string $filename): string
    {
        return $file->storeAs(
            $this->directoryFor($userId),
            $filename,
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

    public function delete(string $path): bool
    {
        return Storage::disk(self::DISK)->delete($path);
    }

    private function filename(): string
    {
        return (string) \Illuminate\Support\Str::uuid().'.replay';
    }
}
