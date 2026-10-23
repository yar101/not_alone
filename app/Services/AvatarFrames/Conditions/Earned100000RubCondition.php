<?php

namespace App\Services\AvatarFrames\Conditions;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\User;

class Earned100000RubCondition implements FrameCondition
{
    public function getKey(): string
    {
        return 'earned_100000_rub';
    }

    public function getDescription(): string
    {
        return 'Получить выплат за заказы на сумму от 100 000 ₽';
    }

    public function check(User $user): bool
    {
        if (! $user->is_idol) {
            return false;
        }

        $total = $user->walletTransactions()
            ->where('type', WalletTransactionType::OrderPayout)
            ->where('status', WalletTransactionStatus::Completed)
            ->sum('amount');

        return $total >= 100000;
    }
}
