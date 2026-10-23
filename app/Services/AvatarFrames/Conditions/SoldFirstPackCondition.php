<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\ContentPackPurchase;
use App\Models\User;

class SoldFirstPackCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'sold_first_pack';
    }

    public function getDescription(): string
    {
        return 'Продать первый контент-пак';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return ContentPackPurchase::whereHas('contentPack', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
