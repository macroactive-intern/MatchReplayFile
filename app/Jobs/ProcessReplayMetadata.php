<?php

namespace App\Jobs;

use App\Models\Replay;
use App\Services\ReplayStorage;
use App\Support\ReplayFormat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessReplayMetadata implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

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
            $this->markProcessing();

            $header = $this->readHeader();

            if (! $this->hasValidMagicBytes($header)) {
                $this->markFailed();

                return;
            }

            $metadata = $this->readMetadata($header);

            $this->replay->forceFill([
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
        return strlen($header) >= ReplayFormat::HEADER_BYTES
            && substr($header, 0, ReplayFormat::MAGIC_BYTES_LENGTH) === ReplayFormat::MAGIC_BYTES;
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

    private function readHeader(): string
    {
        $stream = Storage::disk(ReplayStorage::DISK)->readStream($this->replay->stored_path);

        if ($stream === false) {
            throw new RuntimeException('Unable to open replay file stream.');
        }

        try {
            $header = fread($stream, ReplayFormat::HEADER_BYTES);

            if ($header === false) {
                throw new RuntimeException('Unable to read replay file header.');
            }

            return $header;
        } finally {
            fclose($stream);
        }
    }

    private function markProcessing(): void
    {
        $this->replay->forceFill([
            'status' => Replay::STATUS_PROCESSING,
        ])->save();
    }

    private function markFailed(): void
    {
        $this->replay->forceFill([
            'status' => Replay::STATUS_FAILED,
        ])->save();
    }
}
