<?php

namespace App\Observers;

use App\Models\Replay;
use App\Services\ReplayStorage;
use Illuminate\Support\Facades\Storage;

class ReplayObserver
{
    public function deleted(Replay $replay): void
    {
        Storage::disk(ReplayStorage::DISK)->delete($replay->stored_path);
    }
}
