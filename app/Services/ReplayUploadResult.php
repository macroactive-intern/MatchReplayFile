<?php

namespace App\Services;

use App\Models\Replay;

class ReplayUploadResult
{
    public function __construct(
        public readonly Replay $replay,
        public readonly bool $duplicate,
    ) {
    }

    public static function created(Replay $replay): self
    {
        return new self($replay, false);
    }

    public static function duplicate(Replay $replay): self
    {
        return new self($replay, true);
    }
}
