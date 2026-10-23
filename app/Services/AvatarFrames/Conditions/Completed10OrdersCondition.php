<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Enums\OrderStatus;
use App\Models\User;

class Completed10OrdersCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'completed_10_orders';
    }

    public function getDescription(): string
    {
        return 'Выполнить 10 заказов в качестве айдола';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        return $user->ordersAsIdol()
            ->where('status', OrderStatus::Completed)
            ->count() >= 10;
    }
}
