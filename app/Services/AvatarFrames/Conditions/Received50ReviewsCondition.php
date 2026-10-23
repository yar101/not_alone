<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\Review;
use App\Models\User;

class Received50ReviewsCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'received_50_reviews';
    }

    public function getDescription(): string
    {
        return 'Получить 50 отзывов в качестве айдола';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return Review::where('idol_id', $user->id)
            ->where('is_hidden', false)
            ->count() >= 50;
    }
}
