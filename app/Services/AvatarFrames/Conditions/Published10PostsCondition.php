<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\User;

class Published10PostsCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'published_10_posts';
    }

    public function getDescription(): string
    {
        return 'Опубликовать 10 постов';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return $user->posts()->count() >= 10;
    }
}
