<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Models\User;
use App\Enums\OrderStatus;

class Completed25OrdersCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'completed_25_orders';
    }

    public function getDescription(): string
    {
        return 'Выполнить 25 заказов в качестве айдола';
    }

    public function check(User $user): bool
    {
        // Проверяем только для айдолов
        if (!$user->is_idol) {
            return false;
        }

        $count = $user->ordersAsIdol()
            ->where('status', OrderStatus::Completed)
            ->count();

        return $count >= 25;
    }
}
