<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\User;

class Has10FollowersCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'has_10_followers';
    }

    public function getDescription(): string
    {
        return 'Набрать 10 подписчиков';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return $user->followers()->count() >= 10;
    }
}
