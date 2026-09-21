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
    case OrderClawback = 'order_clawback';
    case OrderHoldRelease = 'order_hold_release';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Пополнение',
            self::Withdrawal => 'Вывод средств',
            self::OrderHold => 'Заморозка',
            self::OrderPayout => 'Оплата за заказ',
            self::OrderRefund => 'Возврат по заказу',
            self::PackPurchase => 'Покупка пака',
            self::PackSale => 'Продажа пака',
            self::PlatformFee => 'Комиссия',
            self::AdminAdjustment => 'Корректировка администратором',
            self::OrderClawback => 'Списание по спору',
            self::OrderHoldRelease => 'Списание из заморозки',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::Deposit, self::OrderPayout, self::OrderRefund, self::PackSale => true,
            self::Withdrawal, self::OrderHold, self::PackPurchase, self::PlatformFee, self::OrderClawback, self::OrderHoldRelease => false,
            self::AdminAdjustment => false,
        };
    }
}
