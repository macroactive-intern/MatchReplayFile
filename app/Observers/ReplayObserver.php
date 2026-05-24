<?php

namespace App\Observers;

use App\Models\Replay;
use Illuminate\Support\Facades\Storage;

class ReplayObserver
{
    public function deleted(Replay $replay): void
    {
        Storage::disk('local')->delete($replay->stored_path);
    }
}
