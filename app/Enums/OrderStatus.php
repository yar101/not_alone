<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
    case Paid      = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded  = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Ожидает',
            self::Accepted  => 'Принят',
            self::Paid      => 'Оплачен',
            self::Completed => 'Выполнен',
            self::Cancelled => 'Отменён',
            self::Refunded  => 'Возвращён',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending   => 'yellow',
            self::Accepted  => 'green',
            self::Paid      => 'blue',
            self::Completed => 'purple',
            self::Cancelled => 'red',
            self::Refunded  => 'orange',
        };
    }
}
