<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\Review;
use App\Models\User;

class HighRating50Condition implements FrameCondition
{
    public function getKey(): string
    {
        return 'high_rating_50';
    }

    public function getDescription(): string
    {
        return 'Достичь рейтинга 50 при наличии не менее 20 отзывов';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        $reviewCount = Review::where('idol_id', $user->id)
            ->where('is_hidden', false)
            ->count();

        if ($reviewCount < 20) {
            return false;
        }

        return $user->rating >= 50;
    }
}
