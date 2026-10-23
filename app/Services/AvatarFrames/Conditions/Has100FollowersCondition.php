<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\User;

class Has100FollowersCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'has_100_followers';
    }

    public function getDescription(): string
    {
        return 'Набрать 100 подписчиков';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return $user->followers()->count() >= 100;
    }
}
