<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guild extends Model
{
    protected $fillable = [
        'name',
    ];

    public function replays(): HasMany
    {
        return $this->hasMany(Replay::class);
    }
}
