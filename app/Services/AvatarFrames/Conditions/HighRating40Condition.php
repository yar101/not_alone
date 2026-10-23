<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\Review;
use App\Models\User;

class HighRating40Condition implements FrameCondition
{
    public function getKey(): string
    {
        return 'high_rating_40';
    }

    public function getDescription(): string
    {
        return 'Достичь рейтинга 40 при наличии не менее 10 отзывов';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        $reviewCount = Review::where('idol_id', $user->id)
            ->where('is_hidden', false)
            ->count();

        if ($reviewCount < 10) {
            return false;
        }

        return $user->rating >= 40;
    }
}
