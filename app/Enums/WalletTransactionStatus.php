<?php

namespace App\Enums;

enum WalletTransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'В обработке',
            self::Completed => 'Завершено',
            self::Failed => 'Ошибка',
            self::Cancelled => 'Отменено',
        };
    }
}
