<?php

namespace App\Policies;

use App\Models\Replay;
use App\Models\User;

class ReplayPolicy
{
    /**
     * @var array<int, array<int, int>>
     */
    private array $guildIdCache = [];

    public function view(User $user, Replay $replay): bool
    {
        return $this->ownsReplay($user, $replay)
            || $this->belongsToReplayGuild($user, $replay);
    }

    public function download(User $user, Replay $replay): bool
    {
        return $this->view($user, $replay);
    }

    public function viewAnalytics(User $user, Replay $replay): bool
    {
        return $this->ownsReplay($user, $replay);
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

        return in_array((int) $replay->guild_id, $this->guildIdsFor($user), true);
    }

    /**
     * @return array<int, int>
     */
    private function guildIdsFor(User $user): array
    {
        if ($user->relationLoaded('guilds')) {
            return $user->guilds
                ->pluck('id')
                ->map(fn (int|string $id): int => (int) $id)
                ->all();
        }

        return $this->guildIdCache[(int) $user->getKey()] ??= $user->guilds()
            ->pluck('guilds.id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }
}
