<?php

namespace App\Policies;

use App\Models\Replay;
use App\Models\User;

class ReplayPolicy
{
    public function view(User $user, Replay $replay): bool
    {
        return $this->ownsReplay($user, $replay)
            || $this->belongsToReplayGuild($user, $replay);
    }

    public function download(User $user, Replay $replay): bool
    {
        return $this->view($user, $replay);
    }

    public function update(User $user, Replay $replay): bool
    {
        return $this->ownsReplay($user, $replay);
    }

    public function delete(User $user, Replay $replay): bool
    {
        return $this->ownsReplay($user, $replay);
    }

    private function ownsReplay(User $user, Replay $replay): bool
    {
        return (int) $replay->user_id === (int) $user->getKey();
    }

    private function belongsToReplayGuild(User $user, Replay $replay): bool
    {
        if ($replay->guild_id === null) {
            return false;
        }

        return $user->guilds()
            ->whereKey($replay->guild_id)
            ->exists();
    }
}
