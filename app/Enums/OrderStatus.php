<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Создан',
            self::Accepted => 'Принят',
            self::Paid => 'Оплачен',
            self::Completed => 'Завершён',
            self::Cancelled => 'Отменён',
            self::Refunded => 'Аннулирован',
            self::Disputed => 'Оспаривается',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Accepted => 'green',
            self::Paid => 'blue',
            self::Completed => 'purple',
            self::Cancelled => 'red',
            self::Refunded => 'orange',
            self::Disputed => 'red',
        };
    }

    public function canTransitionTo(OrderStatus $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Accepted, self::Cancelled]),
            self::Accepted => in_array($target, [self::Paid, self::Cancelled]),
            self::Paid => in_array($target, [self::Completed, self::Disputed, self::Refunded, self::Cancelled]),
            self::Disputed => in_array($target, [self::Completed, self::Refunded, self::Cancelled]),
            self::Completed => false,
            self::Cancelled => false,
            self::Refunded => false,
        };
    }
}
