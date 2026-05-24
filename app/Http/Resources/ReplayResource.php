<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReplayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'game_version' => $this->game_version,
            'status' => $this->status,
            'duration_seconds' => $this->duration_seconds,
            'player_count' => $this->player_count,
            'file_size' => $this->file_size,
            'created_at' => $this->created_at,
            'guild_id' => $this->guild_id,
        ];
    }
}
