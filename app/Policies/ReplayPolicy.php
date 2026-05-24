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

        $cacheKey = 'replay_policy.guild_ids.'.$user->getKey();

        if (request()->attributes->has($cacheKey)) {
            return request()->attributes->get($cacheKey);
        }

        $guildIds = $user->guilds()
            ->pluck('guilds.id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();

        request()->attributes->set($cacheKey, $guildIds);

        return $guildIds;
    }
}
