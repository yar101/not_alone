<?php

namespace App\Services;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Admin;
use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Get the user's wallet or create one if it doesn't exist.
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0.00,
                'held_balance' => 0.00,
                'currency' => 'RUB',
                'is_active' => true,
            ]
        );
    }

    /**
     * Deposit funds into a user's wallet.
     */
    public function deposit(
        User $user,
        float $amount,
        ?string $description = null,
        ?string $idempotencyKey = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма пополнения должна быть больше 0');
        }

        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            throw new \DomainException('Операция недоступна для заблокированного аккаунта.');
        }

        return DB::transaction(function () use ($user, $amount, $description, $idempotencyKey, $metadata) {
            if ($idempotencyKey) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $wallet = $this->getOrCreateWallet($user);
            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if (! $lockedWallet->is_active) {
                throw new \DomainException('Кошелёк деактивирован.');
            }

            $feePercent = (float) PlatformSetting::get('deposit_fee_percent', config('services.payments.deposit_fee_percent', 4.0));
            $feeAmount = $feePercent > 0 ? round($amount * ($feePercent / 100), 2) : 0.0;
            $netAmount = (float) bcsub((string) $amount, (string) $feeAmount, 2);

            if ($feeAmount > 0) {
                $metadata = array_merge($metadata, [
                    'gross_amount' => $amount,
                    'fee_percent' => $feePercent,
                    'fee_amount' => $feeAmount,
                    'net_amount' => $netAmount,
                ]);
            }

            $balanceBefore = $lockedWallet->balance;
            $balanceAfter = (float) bcadd((string) $balanceBefore, (string) $netAmount, 2);

            $lockedWallet->update(['balance' => $balanceAfter]);

            return WalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'user_id' => $user->id,
                'type' => WalletTransactionType::Deposit,
                'amount' => $netAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'held_balance_before' => $lockedWallet->held_balance,
                'held_balance_after' => $lockedWallet->held_balance,
                'status' => WalletTransactionStatus::Completed,
                'idempotency_key' => $idempotencyKey,
                'description' => $description ?? 'Пополнение',
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Hold funds in escrow for an order or operation.
     */
    public function hold(
        User $user,
        float $amount,
        Model $reference,
        ?string $description = null,
        ?string $idempotencyKey = null,
        array $metadata = []
    ): ?WalletTransaction {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $amount, $reference, $description, $idempotencyKey, $metadata) {
            if ($idempotencyKey) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $wallet = $this->getOrCreateWallet($user);
            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if (! $lockedWallet->is_active) {
                throw new \DomainException('Кошелёк деактивирован.');
            }

            if (! $lockedWallet->hasSufficientBalance($amount)) {
                throw new InsufficientFundsException(
                    "Недостаточно средств. Требуется {$amount} руб., доступно {$lockedWallet->balance} руб."
                );
            }

            $balanceBefore = $lockedWallet->balance;
            $balanceAfter = (float) bcsub((string) $balanceBefore, (string) $amount, 2);

            $heldBefore = $lockedWallet->held_balance;
            $heldAfter = (float) bcadd((string) $heldBefore, (string) $amount, 2);

            $lockedWallet->update([
                'balance' => $balanceAfter,
                'held_balance' => $heldAfter,
            ]);

            return WalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'user_id' => $user->id,
                'type' => WalletTransactionType::OrderHold,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'held_balance_before' => $heldBefore,
                'held_balance_after' => $heldAfter,
                'status' => WalletTransactionStatus::Completed,
                'reference_type' => get_class($reference),
                'reference_id' => $reference->getKey(),
                'idempotency_key' => $idempotencyKey,
                'description' => $description ?? "Холд средств по заказу #{$reference->getKey()}",
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Release held escrow funds: deduct from customer's held balance and payout to idol.
     */
    public function releaseHold(Order $order, ?float $platformFeePercent = null): array
    {
        return DB::transaction(function () use ($order, $platformFeePercent) {
            // Check if already paid out
            $existingPayout = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', WalletTransactionType::OrderPayout)
                ->first();

            if ($existingPayout) {
                return ['payout' => $existingPayout];
            }

            // Find hold transaction
            $holdTx = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', WalletTransactionType::OrderHold)
                ->where('status', WalletTransactionStatus::Completed)
                ->first();

            if (! $holdTx) {
                return [];
            }

            $heldAmount = abs((float) $holdTx->amount);
            if ($heldAmount <= 0) {
                return [];
            }

            $customerWallet = $this->getOrCreateWallet($order->customer);
            $idolWallet = $this->getOrCreateWallet($order->idol);

            // Lock wallets in ascending ID order to prevent deadlock
            $firstId = min($customerWallet->id, $idolWallet->id);
            $secondId = max($customerWallet->id, $idolWallet->id);

            $firstLocked = Wallet::where('id', $firstId)->lockForUpdate()->first();
            $secondLocked = Wallet::where('id', $secondId)->lockForUpdate()->first();

            $lockedCustWallet = $customerWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;
            $lockedIdolWallet = $idolWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;

            if (! $lockedCustWallet->is_active || ! $lockedIdolWallet->is_active) {
                throw new \DomainException('Один из кошельков деактивирован.');
            }

            // 1. Release from customer's held_balance
            $custHeldBefore = $lockedCustWallet->held_balance;
            $custHeldAfter = max(0.00, (float) bcsub((string) $custHeldBefore, (string) $heldAmount, 2));
            $lockedCustWallet->update(['held_balance' => $custHeldAfter]);

            // 2. Calculate fee and payout
            $feePercent = $platformFeePercent ?? (float) PlatformSetting::get('platform_fee_percent', config('services.payments.platform_fee_percent', 10.0));
            $feeAmount = round($heldAmount * ($feePercent / 100), 2);

            // 3. Gross payout to idol's balance
            $idolBalBefore = $lockedIdolWallet->balance;
            $idolBalAfterPayout = (float) bcadd((string) $idolBalBefore, (string) $heldAmount, 2);

            $payoutTx = WalletTransaction::create([
                'wallet_id' => $lockedIdolWallet->id,
                'user_id' => $order->idol_id,
                'type' => WalletTransactionType::OrderPayout,
                'amount' => $heldAmount,
                'balance_before' => $idolBalBefore,
                'balance_after' => $idolBalAfterPayout,
                'held_balance_before' => $lockedIdolWallet->held_balance,
                'held_balance_after' => $lockedIdolWallet->held_balance,
                'status' => WalletTransactionStatus::Completed,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'description' => "Выплата за выполнение заказа #{$order->id}",
                'metadata' => [
                    'order_id' => $order->id,
                    'gross_amount' => $heldAmount,
                    'fee_percent' => $feePercent,
                    'fee_amount' => $feeAmount,
                ],
            ]);

            $feeTx = null;
            if ($feeAmount > 0) {
                $idolBalAfterFee = (float) bcsub((string) $idolBalAfterPayout, (string) $feeAmount, 2);
                $lockedIdolWallet->update(['balance' => $idolBalAfterFee]);

                $feeTx = WalletTransaction::create([
                    'wallet_id' => $lockedIdolWallet->id,
                    'user_id' => $order->idol_id,
                    'type' => WalletTransactionType::PlatformFee,
                    'amount' => -$feeAmount,
                    'balance_before' => $idolBalAfterPayout,
                    'balance_after' => $idolBalAfterFee,
                    'held_balance_before' => $lockedIdolWallet->held_balance,
                    'held_balance_after' => $lockedIdolWallet->held_balance,
                    'status' => WalletTransactionStatus::Completed,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'description' => "Комиссия по заказу #{$order->id}",
                    'metadata' => [
                        'order_id' => $order->id,
                        'fee_percent' => $feePercent,
                    ],
                ]);
            } else {
                $lockedIdolWallet->update(['balance' => $idolBalAfterPayout]);
            }

            return [
                'payout' => $payoutTx,
                'fee' => $feeTx,
            ];
        });
    }

    /**
     * Refund held funds back to the customer's available balance upon cancellation/refund.
     */
    public function refundHold(Order $order, ?string $reason = null): ?WalletTransaction
    {
        return DB::transaction(function () use ($order, $reason) {
            // Check if already refunded
            $existingRefund = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', WalletTransactionType::OrderRefund)
                ->first();

            if ($existingRefund) {
                return $existingRefund;
            }

            // Find hold transaction
            $holdTx = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', WalletTransactionType::OrderHold)
                ->where('status', WalletTransactionStatus::Completed)
                ->first();

            if (! $holdTx) {
                return null;
            }

            $heldAmount = abs((float) $holdTx->amount);
            if ($heldAmount <= 0) {
                return null;
            }

            // Check if payout already occurred (order was completed)
            $payoutTx = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', WalletTransactionType::OrderPayout)
                ->where('status', WalletTransactionStatus::Completed)
                ->first();

            $customerWallet = $this->getOrCreateWallet($order->customer);

            if ($payoutTx) {
                // Completed order refund: claw back funds from idol, return funds to customer
                $idolWallet = $this->getOrCreateWallet($order->idol);

                // Lock wallets in ascending ID order to avoid deadlocks
                $firstId = min($customerWallet->id, $idolWallet->id);
                $secondId = max($customerWallet->id, $idolWallet->id);

                $firstLocked = Wallet::where('id', $firstId)->lockForUpdate()->first();
                $secondLocked = Wallet::where('id', $secondId)->lockForUpdate()->first();

                $lockedCustomerWallet = $customerWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;
                $lockedIdolWallet = $idolWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;

                if (! $lockedCustomerWallet->is_active || ! $lockedIdolWallet->is_active) {
                    throw new \DomainException('Один из кошельков деактивирован.');
                }

                $feeTx = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->where('type', WalletTransactionType::PlatformFee)
                    ->first();

                $grossAmount = (float) $payoutTx->amount;
                $feeAmount = $feeTx ? abs((float) $feeTx->amount) : 0.00;
                $netIdolDeduction = (float) bcsub((string) $grossAmount, (string) $feeAmount, 2);

                // Deduct from idol (allows overdraft if idol already withdrew funds)
                $idolBalBefore = $lockedIdolWallet->balance;
                $idolBalAfter = (float) bcsub((string) $idolBalBefore, (string) $netIdolDeduction, 2);
                $lockedIdolWallet->update(['balance' => $idolBalAfter]);

                WalletTransaction::create([
                    'wallet_id' => $lockedIdolWallet->id,
                    'user_id' => $order->idol_id,
                    'type' => WalletTransactionType::OrderClawback,
                    'amount' => -$netIdolDeduction,
                    'balance_before' => $idolBalBefore,
                    'balance_after' => $idolBalAfter,
                    'held_balance_before' => $lockedIdolWallet->held_balance,
                    'held_balance_after' => $lockedIdolWallet->held_balance,
                    'status' => WalletTransactionStatus::Completed,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'description' => "Списание по спору/отмене выполненного заказа #{$order->id}" . ($reason ? ": {$reason}" : ''),
                    'metadata' => [
                        'order_id' => $order->id,
                        'gross_amount' => $grossAmount,
                        'fee_amount' => $feeAmount,
                        'clawback_amount' => $netIdolDeduction,
                        'reason' => $reason,
                    ],
                ]);

                // Refund customer
                $custBalBefore = $lockedCustomerWallet->balance;
                $custBalAfter = (float) bcadd((string) $custBalBefore, (string) $heldAmount, 2);
                $lockedCustomerWallet->update(['balance' => $custBalAfter]);

                return WalletTransaction::create([
                    'wallet_id' => $lockedCustomerWallet->id,
                    'user_id' => $order->customer_id,
                    'type' => WalletTransactionType::OrderRefund,
                    'amount' => $heldAmount,
                    'balance_before' => $custBalBefore,
                    'balance_after' => $custBalAfter,
                    'held_balance_before' => $lockedCustomerWallet->held_balance,
                    'held_balance_after' => $lockedCustomerWallet->held_balance,
                    'status' => WalletTransactionStatus::Completed,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'description' => "Возврат средств по заказу #{$order->id}" . ($reason ? ": {$reason}" : ''),
                    'metadata' => [
                        'order_id' => $order->id,
                        'reason' => $reason,
                        'from_completed' => true,
                    ],
                ]);
            }

            // Normal uncompleted order hold refund
            $lockedWallet = Wallet::where('id', $customerWallet->id)->lockForUpdate()->first();

            if (! $lockedWallet->is_active) {
                throw new \DomainException('Кошелёк деактивирован.');
            }

            $balBefore = $lockedWallet->balance;
            $heldBefore = $lockedWallet->held_balance;

            $heldAfter = max(0.00, (float) bcsub((string) $heldBefore, (string) $heldAmount, 2));
            $balAfter = (float) bcadd((string) $balBefore, (string) $heldAmount, 2);

            $lockedWallet->update([
                'balance' => $balAfter,
                'held_balance' => $heldAfter,
            ]);

            return WalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'user_id' => $order->customer_id,
                'type' => WalletTransactionType::OrderRefund,
                'amount' => $heldAmount,
                'balance_before' => $balBefore,
                'balance_after' => $balAfter,
                'held_balance_before' => $heldBefore,
                'held_balance_after' => $heldAfter,
                'status' => WalletTransactionStatus::Completed,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'description' => "Возврат средств по заказу #{$order->id}" . ($reason ? ": {$reason}" : ''),
                'metadata' => [
                    'order_id' => $order->id,
                    'reason' => $reason,
                ],
            ]);
        });
    }

    /**
     * Purchase a content pack with wallet transactions or mock mode.
     */
    public function purchaseContentPack(User $buyer, ContentPack $pack, ?float $platformFeePercent = null): ContentPackPurchase
    {
        if ($buyer->id === $pack->user_id) {
            throw new \DomainException('Нельзя покупать собственный контент-пак');
        }

        $alreadyPurchased = ContentPackPurchase::where('content_pack_id', $pack->id)
            ->where('user_id', $buyer->id)
            ->exists();
        if ($alreadyPurchased) {
            throw new \DomainException('Контент-пак уже приобретён.');
        }

        $isMock = config('services.payments.mock_purchases', true);
        $price = (float) ($pack->price ?? 0);

        return DB::transaction(function () use ($buyer, $pack, $price, $isMock, $platformFeePercent) {
            if (! $isMock && $price > 0) {
                $seller = $pack->user;
                $buyerWallet = $this->getOrCreateWallet($buyer);
                $sellerWallet = $this->getOrCreateWallet($seller);

                // Lock wallets in ascending ID order to avoid deadlocks
                $firstId = min($buyerWallet->id, $sellerWallet->id);
                $secondId = max($buyerWallet->id, $sellerWallet->id);

                $firstLocked = Wallet::where('id', $firstId)->lockForUpdate()->first();
                $secondLocked = Wallet::where('id', $secondId)->lockForUpdate()->first();

                $lockedBuyerWallet = $buyerWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;
                $lockedSellerWallet = $sellerWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;

                if (! $lockedBuyerWallet->is_active || ! $lockedSellerWallet->is_active) {
                    throw new \DomainException('Один из кошельков деактивирован.');
                }

                if (! $lockedBuyerWallet->hasSufficientBalance($price)) {
                    throw new InsufficientFundsException(
                        "Недостаточно средств для покупки пака ({$price} руб.)"
                    );
                }

                // 1. Deduct from buyer
                $buyerBalBefore = $lockedBuyerWallet->balance;
                $buyerBalAfter = (float) bcsub((string) $buyerBalBefore, (string) $price, 2);
                $lockedBuyerWallet->update(['balance' => $buyerBalAfter]);

                $buyerTx = WalletTransaction::create([
                    'wallet_id' => $lockedBuyerWallet->id,
                    'user_id' => $buyer->id,
                    'type' => WalletTransactionType::PackPurchase,
                    'amount' => -$price,
                    'balance_before' => $buyerBalBefore,
                    'balance_after' => $buyerBalAfter,
                    'held_balance_before' => $lockedBuyerWallet->held_balance,
                    'held_balance_after' => $lockedBuyerWallet->held_balance,
                    'status' => WalletTransactionStatus::Completed,
                    'reference_type' => ContentPack::class,
                    'reference_id' => $pack->id,
                    'description' => "Покупка контент-пака «{$pack->title}»",
                    'metadata' => ['pack_id' => $pack->id],
                ]);

                // 2. Credit seller gross, then deduct fee
                $feePercent = $platformFeePercent ?? (float) PlatformSetting::get('platform_fee_percent', config('services.payments.platform_fee_percent', 10.0));
                $feeAmount = round($price * ($feePercent / 100), 2);

                $sellerBalBefore = $lockedSellerWallet->balance;
                $sellerBalAfterGross = (float) bcadd((string) $sellerBalBefore, (string) $price, 2);

                WalletTransaction::create([
                    'wallet_id' => $lockedSellerWallet->id,
                    'user_id' => $seller->id,
                    'type' => WalletTransactionType::PackSale,
                    'amount' => $price,
                    'balance_before' => $sellerBalBefore,
                    'balance_after' => $sellerBalAfterGross,
                    'held_balance_before' => $lockedSellerWallet->held_balance,
                    'held_balance_after' => $lockedSellerWallet->held_balance,
                    'status' => WalletTransactionStatus::Completed,
                    'reference_type' => ContentPack::class,
                    'reference_id' => $pack->id,
                    'description' => "Продажа контент-пака «{$pack->title}»",
                    'metadata' => [
                        'pack_id' => $pack->id,
                        'buyer_id' => $buyer->id,
                        'gross_amount' => $price,
                        'fee_percent' => $feePercent,
                        'fee_amount' => $feeAmount,
                    ],
                ]);

                if ($feeAmount > 0) {
                    $sellerBalAfterFee = (float) bcsub((string) $sellerBalAfterGross, (string) $feeAmount, 2);
                    $lockedSellerWallet->update(['balance' => $sellerBalAfterFee]);

                    WalletTransaction::create([
                        'wallet_id' => $lockedSellerWallet->id,
                        'user_id' => $seller->id,
                        'type' => WalletTransactionType::PlatformFee,
                        'amount' => -$feeAmount,
                        'balance_before' => $sellerBalAfterGross,
                        'balance_after' => $sellerBalAfterFee,
                        'held_balance_before' => $lockedSellerWallet->held_balance,
                        'held_balance_after' => $lockedSellerWallet->held_balance,
                        'status' => WalletTransactionStatus::Completed,
                        'reference_type' => ContentPack::class,
                        'reference_id' => $pack->id,
                        'description' => "Комиссия за контент-пак «{$pack->title}»",
                        'metadata' => ['pack_id' => $pack->id, 'fee_percent' => $feePercent],
                    ]);
                } else {
                    $lockedSellerWallet->update(['balance' => $sellerBalAfterGross]);
                }
            }

            return ContentPackPurchase::firstOrCreate(
                [
                    'content_pack_id' => $pack->id,
                    'user_id' => $buyer->id,
                ],
                [
                    'price_paid' => $price,
                    'purchased_at' => now(),
                ]
            );
        });
    }

    /**
     * Withdraw funds from an idol's available wallet balance.
     */
    public function withdraw(
        User $user,
        float $amount,
        ?string $idempotencyKey = null,
        ?string $description = null,
        ?array $metadata = null
    ): WalletTransaction {
        if (! $user->is_idol) {
            throw new \DomainException('Вывод средств разрешён только айдолам.');
        }

        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            throw new \DomainException('Операция недоступна для заблокированного аккаунта.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма вывода должна быть больше нуля.');
        }

        return DB::transaction(function () use ($user, $amount, $idempotencyKey, $description, $metadata) {
            if ($idempotencyKey) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $wallet = $this->getOrCreateWallet($user);
            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if (! $lockedWallet->is_active) {
                throw new \DomainException('Кошелёк деактивирован.');
            }

            if (! $lockedWallet->hasSufficientBalance($amount)) {
                throw new InsufficientFundsException(
                    "Недостаточно средств для вывода. Доступно: {$lockedWallet->balance} руб., запрошено: {$amount} руб."
                );
            }

            $feePercent = (float) PlatformSetting::get('withdrawal_fee_percent', config('services.payments.withdrawal_fee_percent', 4.0));
            $feeAmount = $feePercent > 0 ? round($amount * ($feePercent / 100), 2) : 0.0;
            $payoutAmount = (float) bcsub((string) $amount, (string) $feeAmount, 2);

            $metadata = $metadata ?? [];
            if ($feeAmount > 0) {
                $metadata = array_merge($metadata, [
                    'requested_amount' => $amount,
                    'fee_percent' => $feePercent,
                    'fee_amount' => $feeAmount,
                    'payout_amount' => $payoutAmount,
                ]);
            }

            $balanceBefore = $lockedWallet->balance;
            $balanceAfter = (float) bcsub((string) $balanceBefore, (string) $amount, 2);

            $lockedWallet->update(['balance' => $balanceAfter]);

            return WalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'user_id' => $user->id,
                'type' => WalletTransactionType::Withdrawal,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'held_balance_before' => $lockedWallet->held_balance,
                'held_balance_after' => $lockedWallet->held_balance,
                'status' => WalletTransactionStatus::Completed,
                'idempotency_key' => $idempotencyKey,
                'description' => $description ?? 'Вывод средств со счёта',
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Perform an administrative balance adjustment (credit or debit).
     */
    public function adminAdjust(
        User $user,
        float $amount,
        string $direction,
        ?string $description = null,
        ?int $adminId = null,
        ?string $type = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма операции должна быть больше нуля.');
        }

        if (! in_array($direction, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException('Некорректное направление операции.');
        }

        return DB::transaction(function () use ($user, $amount, $direction, $description, $adminId, $type) {
            $wallet = $this->getOrCreateWallet($user);
            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if (! $lockedWallet->is_active) {
                throw new \DomainException('Кошелёк пользователя деактивирован.');
            }

            $balanceBefore = $lockedWallet->balance;
            $heldBalance = $lockedWallet->held_balance;

            if ($direction === 'debit') {
                if (! $lockedWallet->hasSufficientBalance($amount)) {
                    throw new InsufficientFundsException(
                        "Недостаточно средств на балансе пользователя. Доступно: {$balanceBefore} руб., запрошено к списанию: {$amount} руб."
                    );
                }
                $signedAmount = -$amount;
                $balanceAfter = (float) bcsub((string) $balanceBefore, (string) $amount, 2);
            } else {
                $signedAmount = $amount;
                $balanceAfter = (float) bcadd((string) $balanceBefore, (string) $amount, 2);
            }

            $lockedWallet->update(['balance' => $balanceAfter]);

            $txType = match ($type) {
                'deposit' => WalletTransactionType::Deposit,
                'withdrawal' => WalletTransactionType::Withdrawal,
                default => WalletTransactionType::AdminAdjustment,
            };

            $admin = $adminId ? Admin::find($adminId) : (auth('admin')->check() ? auth('admin')->user() : null);

            $metadata = [
                'admin_id' => $adminId ?? (auth('admin')->check() ? auth('admin')->id() : null),
                'admin_name' => $admin?->name ?? 'Администратор',
                'direction' => $direction,
                'reason' => $description,
            ];

            $tx = WalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'user_id' => $user->id,
                'type' => $txType,
                'amount' => $signedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'held_balance_before' => $heldBalance,
                'held_balance_after' => $heldBalance,
                'status' => WalletTransactionStatus::Completed,
                'description' => $description ?: ($direction === 'credit' ? 'Начисление администратором' : 'Списание администратором'),
                'metadata' => $metadata,
            ]);

            $actingAdminId = $adminId ?? (auth('admin')->check() ? auth('admin')->id() : null);
            if ($actingAdminId) {
                \App\Services\AdminLogService::log(
                    $actingAdminId,
                    'wallet_adjustment',
                    'wallet',
                    $lockedWallet->id,
                    [
                        'user_id' => $user->id,
                        'transaction_id' => $tx->id,
                        'direction' => $direction,
                        'amount' => $amount,
                        'reason' => $description,
                    ]
                );
            }

            return $tx;
        });
    }
}
