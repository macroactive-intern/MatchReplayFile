<?php

namespace App\Services;

use App\Jobs\ProcessReplayMetadata;
use App\Models\Replay;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class ReplayService
{
    public function __construct(private readonly ReplayStorage $storage)
    {
    }

    public function uploadReplay(User $user, array $validatedData): ReplayUploadResult
    {
        /** @var UploadedFile $file */
        $file = $validatedData['file'];
        $sha256Hash = $this->sha256Hash($file);

        $duplicate = $this->findDuplicate($user, $sha256Hash);

        if ($duplicate !== null) {
            return ReplayUploadResult::duplicate($duplicate);
        }

        $filename = $this->filename();
        $storedPath = $this->storage->storeAs($file, $user->getKey(), $filename);
        $now = now();

        $inserted = Replay::query()->insertOrIgnore([
            'user_id' => $user->getKey(),
            'guild_id' => $validatedData['guild_id'] ?? null,
            'title' => $validatedData['title'],
            'game_version' => $validatedData['game_version'],
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'sha256_hash' => $sha256Hash,
            'status' => Replay::STATUS_UPLOADED,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            $duplicate = $this->findDuplicate($user, $sha256Hash);
            $this->storage->delete($storedPath);

            if ($duplicate === null) {
                throw new RuntimeException('Unable to create or find replay upload record.');
            }

            return ReplayUploadResult::duplicate($duplicate);
        }

        $replay = Replay::query()
            ->where('stored_path', $storedPath)
            ->firstOrFail();

        ProcessReplayMetadata::dispatch($replay);

        return ReplayUploadResult::created($replay);
    }

    private function filename(): string
    {
        return Str::uuid().'.replay';
    }

    private function findDuplicate(User $user, string $sha256Hash): ?Replay
    {
        return Replay::query()
            ->where('user_id', $user->getKey())
            ->where('sha256_hash', $sha256Hash)
            ->first();
    }

    private function sha256Hash(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Unable to read uploaded replay file.');
        }

        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new RuntimeException('Unable to hash uploaded replay file.');
        }

        return $hash;
    }
}
