<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Replay extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'guild_id',
        'title',
        'game_version',
        'original_filename',
        'stored_path',
        'file_size',
        'mime_type',
        'sha256_hash',
        'duration_seconds',
        'player_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
            'player_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ReplayShare::class);
    }

    public function accessEvents(): HasMany
    {
        return $this->hasMany(ReplayAccessEvent::class);
    }
}
