<?php

namespace App\Jobs;

use App\Models\Replay;
use App\Services\ReplayStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessReplayMetadata implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    private const REPLAY_MAGIC_BYTES = "REPQ";

    private const HEADER_BYTES = 16;

    /**
     * Create a new job instance.
     */
    public function __construct(public Replay $replay)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $contents = Storage::disk(ReplayStorage::DISK)->get($this->replay->stored_path);
            $header = substr($contents, 0, self::HEADER_BYTES);

            if (! $this->hasValidMagicBytes($header)) {
                $this->markFailed();

                return;
            }

            $metadata = $this->readMetadata($header);

            $this->replay->forceFill([
                'sha256_hash' => hash('sha256', $contents),
                'duration_seconds' => $metadata['duration_seconds'],
                'player_count' => $metadata['player_count'],
                'status' => Replay::STATUS_READY,
            ])->save();
        } catch (Throwable) {
            $this->markFailed();
        }
    }

    private function hasValidMagicBytes(string $header): bool
    {
        return strlen($header) >= self::HEADER_BYTES
            && substr($header, 0, 4) === self::REPLAY_MAGIC_BYTES;
    }

    /**
     * @return array{duration_seconds: int, player_count: int}
     */
    private function readMetadata(string $header): array
    {
        $duration = unpack('Nduration_seconds', substr($header, 4, 4));
        $players = unpack('nplayer_count', substr($header, 8, 2));

        return [
            'duration_seconds' => $duration['duration_seconds'],
            'player_count' => $players['player_count'],
        ];
    }

    private function markFailed(): void
    {
        $this->replay->forceFill([
            'status' => Replay::STATUS_FAILED,
        ])->save();
    }
}
