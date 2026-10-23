<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\PostLike;
use App\Models\User;

class PostLiked100TimesCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'post_liked_100_times';
    }

    public function getDescription(): string
    {
        return 'Получить 100 лайков на посты суммарно';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return PostLike::whereHas('post', fn ($q) => $q->where('user_id', $user->id))
            ->count() >= 100;
    }
}
