<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\ContentPackPurchase;
use App\Models\User;

class Sold10PacksCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'sold_10_packs';
    }

    public function getDescription(): string
    {
        return 'Продать 10 контент-паков';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return ContentPackPurchase::whereHas('contentPack', fn ($q) => $q->where('user_id', $user->id))
            ->count() >= 10;
    }
}
