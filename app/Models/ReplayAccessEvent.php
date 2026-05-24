<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplayAccessEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'replay_id',
        'replay_share_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function replay(): BelongsTo
    {
        return $this->belongsTo(Replay::class);
    }

    public function replayShare(): BelongsTo
    {
        return $this->belongsTo(ReplayShare::class);
    }
}
