<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\User;

class Created5PacksCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'created_5_packs';
    }

    public function getDescription(): string
    {
        return 'Создать 5 опубликованных контент-паков с не менее 10 фото в каждом';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return $user->contentPacks()
            ->whereNotNull('published_at')
            ->has('photos', '>=', 10)
            ->count() >= 5;
    }
}
