<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplayShare extends Model
{
    public const SCOPE_LINK = 'link';

    public const SCOPE_GUILD = 'guild';

    protected $fillable = [
        'replay_id',
        'shared_by',
        'scope',
        'token',
        'expires_at',
        'access_count',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'access_count' => 'integer',
        ];
    }

    public function replay(): BelongsTo
    {
        return $this->belongsTo(Replay::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
