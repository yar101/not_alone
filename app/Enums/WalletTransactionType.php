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
    case OrderHoldCancelled = 'order_hold_cancelled';
    case CustomerServiceFee = 'customer_service_fee';
    case PinkToBlueConversion = 'pink_to_blue';
    case BlueFromPinkConversion = 'blue_from_pink';
    case DonationSent = 'donation_sent';
    case DonationReceived = 'donation_received';
    case DonationFee = 'donation_fee';

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
            self::OrderHoldRelease => 'Завершение заказа',
            self::OrderHoldCancelled => 'Отмена холда',
            self::CustomerServiceFee => 'Сервисный сбор',
            self::PinkToBlueConversion => 'Перевод в синие монеты',
            self::BlueFromPinkConversion => 'Зачисление из розовых монет',
            self::DonationSent => 'Отправка доната',
            self::DonationReceived => 'Получение доната',
            self::DonationFee => 'Комиссия за донат',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::Deposit, self::OrderPayout, self::OrderRefund, self::PackSale, self::BlueFromPinkConversion, self::DonationReceived => true,
            self::Withdrawal, self::OrderHold, self::PackPurchase, self::PlatformFee, self::OrderClawback, self::OrderHoldRelease, self::OrderHoldCancelled, self::CustomerServiceFee, self::PinkToBlueConversion, self::DonationSent, self::DonationFee => false,
            self::AdminAdjustment => false,
        };
    }
}
