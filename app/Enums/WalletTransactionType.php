<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case OrderHold = 'order_hold';
    case OrderPayout = 'order_payout';
    case OrderRefund = 'order_refund';
    case PackPurchase = 'pack_purchase';
    case PackSale = 'pack_sale';
    case PlatformFee = 'platform_fee';
    case AdminAdjustment = 'admin_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Пополнение баланса',
            self::Withdrawal => 'Вывод средств',
            self::OrderHold => 'Холд по заказу',
            self::OrderPayout => 'Выплата за заказ',
            self::OrderRefund => 'Возврат по заказу',
            self::PackPurchase => 'Покупка пака',
            self::PackSale => 'Продажа пака',
            self::PlatformFee => 'Комиссия сервиса',
            self::AdminAdjustment => 'Корректировка администратором',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::Deposit, self::OrderPayout, self::OrderRefund, self::PackSale => true,
            self::Withdrawal, self::OrderHold, self::PackPurchase, self::PlatformFee => false,
            self::AdminAdjustment => false,
        };
    }
}
