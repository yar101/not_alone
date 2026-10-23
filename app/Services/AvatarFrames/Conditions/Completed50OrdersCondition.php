<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Enums\OrderStatus;
use App\Models\User;

class Completed50OrdersCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'completed_50_orders';
    }

    public function getDescription(): string
    {
        return 'Выполнить 50 заказов в качестве айдола';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return $user->ordersAsIdol()
            ->where('status', OrderStatus::Completed)
            ->count() >= 50;
    }
}
