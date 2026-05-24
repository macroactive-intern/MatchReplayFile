<?php

namespace App\Services;

use App\Jobs\ProcessReplayMetadata;
use App\Models\Replay;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ReplayService
{
    public function __construct(private readonly ReplayStorage $storage)
    {
    }

    public function uploadReplay(User $user, array $validatedData): Replay
    {
        /** @var UploadedFile $file */
        $file = $validatedData['file'];
        $filename = $this->filename();
        $storedPath = $this->storage->storeAs($file, $user->getKey(), $filename);

        $replay = Replay::create([
            'user_id' => $user->getKey(),
            'guild_id' => $validatedData['guild_id'] ?? null,
            'title' => $validatedData['title'],
            'game_version' => $validatedData['game_version'],
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'status' => Replay::STATUS_UPLOADED,
        ]);

        ProcessReplayMetadata::dispatch($replay);

        return $replay;
    }

    private function filename(): string
    {
        return Str::uuid().'.replay';
    }
}
