<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Events\ContentPackPurchasedEvent;
use App\Events\NewNotification;
use App\Exceptions\InsufficientFundsException;
use App\Jobs\ReleaseIdolOrderHoldJob;
use App\Models\Admin;
use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\IdolPayoutReleasedNotification;
use App\Traits\SafeBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    use SafeBroadcast;

    public const CRITICAL_OVERDRAFT_THRESHOLD = -40000.00;

    /**
     * Calculate idol fee coverage parameters using Option A reverse calculation.
     *
     * @return array{
     *     amount_charged: float,
     *     net_payout: float,
     *     platform_fee: float,
     *     expected_withdrawal_payout: float,
     *     platform_fee_percent: float,
     *     withdrawal_fee_percent: float
     * }
     */
    public function calculateFeeCoverage(
        float $nominalAmount,
        bool $coverPlatform = false,
        bool $coverWithdrawal = false,
        ?float $customPlatformFeePercent = null,
        ?float $customWithdrawalFeePercent = null
    ): array {
        $pPercent = $customPlatformFeePercent ?? (float) PlatformSetting::get('platform_fee_percent', config('services.payments.platform_fee_percent', 10.0));
        $wPercent = $customWithdrawalFeePercent ?? (float) PlatformSetting::get('withdrawal_fee_percent', config('services.payments.withdrawal_fee_percent', 4.0));

        if ($pPercent < 0 || $pPercent >= 100) {
            throw new \InvalidArgumentException('Процент комиссии платформы должен быть в диапазоне от 0 до 99.99.');
        }
        if ($wPercent < 0 || $wPercent >= 100) {
            throw new \InvalidArgumentException('Процент комиссии на вывод должен быть в диапазоне от 0 до 99.99.');
        }

        $pFactor = $coverPlatform ? ((100.0 - $pPercent) / 100.0) : 1.0;
        $wFactor = $coverWithdrawal ? ((100.0 - $wPercent) / 100.0) : 1.0;

        $combinedFactor = $pFactor * $wFactor;
        if ($combinedFactor <= 0.0001) {
            throw new \InvalidArgumentException('Недопустимый совокупный коэффициент комиссии.');
        }

        $amountCharged = round((float) bcdiv((string) $nominalAmount, (string) $combinedFactor, 4), 2, PHP_ROUND_HALF_UP);
        $platformFee = $this->roundFee($amountCharged, $pPercent);
        $netPayout = (float) bcsub((string) $amountCharged, (string) $platformFee, 2);
        $withdrawalFee = $this->roundFee($netPayout, $wPercent);
        $expectedWithdrawalPayout = (float) bcsub((string) $netPayout, (string) $withdrawalFee, 2);

        return [
            'amount_charged' => $amountCharged,
            'net_payout' => $netPayout,
            'platform_fee' => $platformFee,
            'expected_withdrawal_payout' => $expectedWithdrawalPayout,
            'platform_fee_percent' => $pPercent,
            'withdrawal_fee_percent' => $wPercent,
        ];
    }

    /**
     * Get the user's wallet or create one if it doesn't exist.
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
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
        array $metadata = [],
        bool $applyFee = true
    ): WalletTransaction {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма пополнения должна быть больше 0');
        }

        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            throw new \DomainException('Операция недоступна для заблокированного аккаунта.');
        }

        $wallet = $this->getOrCreateWallet($user);

        if ($idempotencyKey) {
            $existing = $this->findExistingIdempotentTransaction($wallet, $idempotencyKey, WalletTransactionType::Deposit, $amount);
            if ($existing) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($user, $amount, $description, $idempotencyKey, $metadata, $applyFee, $wallet) {
                $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

                if (! $lockedWallet->is_active) {
                    throw new \DomainException('Кошелёк деактивирован.');
                }

                if ($idempotencyKey) {
                    $existing = $this->findExistingIdempotentTransaction($lockedWallet, $idempotencyKey, WalletTransactionType::Deposit, $amount);
                    if ($existing) {
                        return $existing;
                    }
                }

                $feePercent = $applyFee
                    ? (float) PlatformSetting::get('deposit_fee_percent', config('services.payments.deposit_fee_percent', 4.0))
                    : 0.0;
                $feeAmount = $this->roundFee($amount, $feePercent);
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

                $lockedWallet->forceFill(['balance' => $balanceAfter])->save();

                return WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'user_id' => $user->id,
                    'type' => WalletTransactionType::Deposit,
                    'currency' => 'blue',
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
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return $this->handleIdempotencyConflict(
                $e,
                $wallet,
                $idempotencyKey,
                WalletTransactionType::Deposit,
                $amount
            );
        }
    }

    /**
     * Check if an order currently has an active escrow hold.
     */
    public function hasActiveHold(Order $order): bool
    {
        $latestHoldId = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::OrderHold)
            ->where('status', WalletTransactionStatus::Completed)
            ->max('id');

        if (! $latestHoldId) {
            return false;
        }

        $latestClosingId = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->whereIn('type', [
                WalletTransactionType::OrderRefund,
                WalletTransactionType::OrderPayout,
                WalletTransactionType::OrderHoldRelease,
            ])
            ->max('id');

        if (! $latestClosingId) {
            return true;
        }

        return $latestHoldId > $latestClosingId;
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
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return null;
        }

        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            throw new \DomainException('Операция недоступна для заблокированного аккаунта.');
        }

        $wallet = $this->getOrCreateWallet($user);

        if ($reference instanceof Order) {
            if ($this->hasActiveHold($reference)) {
                return WalletTransaction::where('wallet_id', $wallet->id)
                    ->where('reference_type', Order::class)
                    ->where('reference_id', $reference->getKey())
                    ->where('type', WalletTransactionType::OrderHold)
                    ->where('status', WalletTransactionStatus::Completed)
                    ->latest('id')
                    ->first();
            }
            $existingCount = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $reference->getKey())
                ->where('type', WalletTransactionType::OrderHold)
                ->count();
            $effectiveIdempotencyKey = $idempotencyKey
                ? ($existingCount > 0 ? "{$idempotencyKey}_{$existingCount}" : $idempotencyKey)
                : "order_hold_{$reference->getKey()}".($existingCount > 0 ? "_{$existingCount}" : '');
        } else {
            $effectiveIdempotencyKey = $idempotencyKey;
        }

        if ($effectiveIdempotencyKey) {
            $existing = $this->findExistingIdempotentTransaction($wallet, $effectiveIdempotencyKey, WalletTransactionType::OrderHold, $amount);
            if ($existing) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($user, $amount, $reference, $description, $effectiveIdempotencyKey, $metadata, $wallet) {
                if ($reference instanceof Order && $this->hasActiveHold($reference)) {
                    return WalletTransaction::where('wallet_id', $wallet->id)
                        ->where('reference_type', Order::class)
                        ->where('reference_id', $reference->getKey())
                        ->where('type', WalletTransactionType::OrderHold)
                        ->where('status', WalletTransactionStatus::Completed)
                        ->latest('id')
                        ->first();
                }

                $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

                if (! $lockedWallet->is_active) {
                    throw new \DomainException('Кошелёк деактивирован.');
                }

                if ($effectiveIdempotencyKey) {
                    $existing = $this->findExistingIdempotentTransaction($lockedWallet, $effectiveIdempotencyKey, WalletTransactionType::OrderHold, $amount);
                    if ($existing) {
                        return $existing;
                    }
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

                $lockedWallet->forceFill([
                    'balance' => $balanceAfter,
                    'held_balance' => $heldAfter,
                ])->save();

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
                    'idempotency_key' => $effectiveIdempotencyKey,
                    'description' => $description ?? "Холд средств по заказу #{$reference->getKey()}",
                    'metadata' => $metadata,
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return $this->handleIdempotencyConflict(
                $e,
                $wallet,
                $effectiveIdempotencyKey,
                WalletTransactionType::OrderHold,
                $amount
            );
        }
    }

    /**
     * Hold order service price in escrow and charge customer service fee.
     *
     * @throws InsufficientFundsException
     */
    public function holdOrderWithFee(
        User $customer,
        Order $order,
        float $servicePrice,
        float $serviceFee,
        float $feePercent,
        float $idolFeeCovered = 0.0
    ): array {
        $servicePrice = round($servicePrice, 2);
        $serviceFee = round($serviceFee, 2);
        $idolFeeCovered = round(max(0.0, $idolFeeCovered), 2);
        $holdAmount = (float) bcadd((string) $servicePrice, (string) $idolFeeCovered, 2);

        $totalRequired = (float) bcadd((string) $holdAmount, (string) $serviceFee, 2);
        $wallet = $this->getOrCreateWallet($customer);

        return DB::transaction(function () use ($customer, $order, $servicePrice, $serviceFee, $feePercent, $idolFeeCovered, $holdAmount, $totalRequired, $wallet) {
            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if (! $lockedWallet->is_active) {
                throw new \DomainException('Кошелёк деактивирован.');
            }

            if (! $lockedWallet->hasSufficientBalance($totalRequired)) {
                throw new InsufficientFundsException(
                    "Недостаточно средств для оплаты заказа. Требуется: {$totalRequired}, доступно: {$lockedWallet->balance}"
                );
            }

            // 1. Charge non-refundable customer service fee if > 0
            $feeTx = null;
            if ($serviceFee > 0) {
                $balBeforeFee = (float) $lockedWallet->balance;
                $balAfterFee = (float) bcsub((string) $balBeforeFee, (string) $serviceFee, 2);

                $lockedWallet->forceFill(['balance' => $balAfterFee])->save();

                $feeTx = WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'user_id' => $customer->id,
                    'type' => WalletTransactionType::CustomerServiceFee,
                    'currency' => 'blue',
                    'amount' => -$serviceFee,
                    'balance_before' => $balBeforeFee,
                    'balance_after' => $balAfterFee,
                    'held_balance_before' => (float) $lockedWallet->held_balance,
                    'held_balance_after' => (float) $lockedWallet->held_balance,
                    'status' => WalletTransactionStatus::Completed,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'idempotency_key' => "cust_fee_order_{$order->id}",
                    'description' => "Сервисный сбор по заказу #{$order->id} ({$feePercent}%)",
                    'metadata' => [
                        'order_id' => $order->id,
                        'service_price' => $servicePrice,
                        'fee_percent' => $feePercent,
                        'fee_amount' => $serviceFee,
                        'idol_fee_covered' => $idolFeeCovered,
                    ],
                ]);
            }

            // 2. Hold service price + covered fee in escrow
            $holdTx = null;
            if ($holdAmount > 0) {
                $balBeforeHold = (float) $lockedWallet->balance;
                $balAfterHold = (float) bcsub((string) $balBeforeHold, (string) $holdAmount, 2);

                $heldBeforeHold = (float) $lockedWallet->held_balance;
                $heldAfterHold = (float) bcadd((string) $heldBeforeHold, (string) $holdAmount, 2);

                $lockedWallet->forceFill([
                    'balance' => $balAfterHold,
                    'held_balance' => $heldAfterHold,
                ])->save();

                $desc = $idolFeeCovered > 0
                    ? "Оплата услуг по заказу #{$order->id} (с покрытием комиссий айдола)"
                    : "Оплата услуг по заказу #{$order->id}";

                $holdTx = WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'user_id' => $customer->id,
                    'type' => WalletTransactionType::OrderHold,
                    'currency' => 'blue',
                    'amount' => -$holdAmount,
                    'balance_before' => $balBeforeHold,
                    'balance_after' => $balAfterHold,
                    'held_balance_before' => $heldBeforeHold,
                    'held_balance_after' => $heldAfterHold,
                    'status' => WalletTransactionStatus::Completed,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'idempotency_key' => "order_hold_{$order->id}",
                    'description' => $desc,
                    'metadata' => [
                        'order_id' => $order->id,
                        'service_price' => $servicePrice,
                        'idol_fee_covered' => $idolFeeCovered,
                        'held_amount' => $holdAmount,
                        'fee_covered_platform' => (bool) $order->fee_covered_platform,
                        'fee_covered_withdrawal' => (bool) $order->fee_covered_withdrawal,
                    ],
                ]);
            }

            return ['fee_transaction' => $feeTx, 'hold_transaction' => $holdTx];
        });
    }

    /**
     * Release held escrow funds: deduct from customer's held balance and payout to idol.
     */
    public function releaseHold(Order $order, ?float $platformFeePercent = null): array
    {
        if ($platformFeePercent !== null && ($platformFeePercent < 0 || $platformFeePercent > 100)) {
            throw new \InvalidArgumentException('Процент комиссии платформы должен быть в диапазоне от 0 до 100.');
        }

        return DB::transaction(function () use ($order, $platformFeePercent) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            if (! $lockedOrder) {
                return [];
            }

            // Find latest active hold transaction
            $holdTx = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $lockedOrder->id)
                ->where('type', WalletTransactionType::OrderHold)
                ->where('status', WalletTransactionStatus::Completed)
                ->latest('id')
                ->first();

            if (! $holdTx) {
                return [];
            }

            // Check if already refunded for this hold
            $existingRefund = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $lockedOrder->id)
                ->where('type', WalletTransactionType::OrderRefund)
                ->where('id', '>', $holdTx->id)
                ->first();

            if ($existingRefund) {
                return []; // Нельзя выплачивать за заказ, по которому оформлен возврат!
            }

            // Check if already paid out for this hold
            $existingPayout = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $lockedOrder->id)
                ->where('type', WalletTransactionType::OrderPayout)
                ->where('id', '>', $holdTx->id)
                ->where('status', '!=', WalletTransactionStatus::Cancelled)
                ->first();

            if ($existingPayout) {
                $existingFee = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $lockedOrder->id)
                    ->where('type', WalletTransactionType::PlatformFee)
                    ->where('id', '>', $holdTx->id)
                    ->where('status', '!=', WalletTransactionStatus::Cancelled)
                    ->first();

                return ['payout' => $existingPayout, 'fee' => $existingFee];
            }

            $heldAmount = abs((float) $holdTx->amount);
            if ($heldAmount <= 0) {
                return [];
            }

            if (! $lockedOrder->customer || ! $lockedOrder->idol) {
                throw new \DomainException('Заказчик или исполнитель не найден.');
            }

            $customerWallet = $this->getOrCreateWallet($lockedOrder->customer);
            $idolWallet = $this->getOrCreateWallet($lockedOrder->idol);

            // Lock wallets in ascending ID order to prevent deadlock
            $firstId = min($customerWallet->id, $idolWallet->id);
            $secondId = max($customerWallet->id, $idolWallet->id);

            $firstLocked = Wallet::where('id', $firstId)->lockForUpdate()->first();
            $secondLocked = ($firstId !== $secondId)
                ? Wallet::where('id', $secondId)->lockForUpdate()->first()
                : $firstLocked;

            $lockedCustWallet = $customerWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;
            $lockedIdolWallet = $idolWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;

            if (! $lockedCustWallet->is_active || ! $lockedIdolWallet->is_active) {
                throw new \DomainException('Один из кошельков деактивирован.');
            }

            // 1. Release from customer's held_balance
            $custHeldBefore = $lockedCustWallet->held_balance;
            $custHeldAfter = max(0.00, (float) bcsub((string) $custHeldBefore, (string) $heldAmount, 2));
            $lockedCustWallet->forceFill(['held_balance' => $custHeldAfter])->save();

            WalletTransaction::create([
                'wallet_id' => $lockedCustWallet->id,
                'user_id' => $lockedOrder->customer_id,
                'type' => WalletTransactionType::OrderHoldRelease,
                'currency' => 'blue',
                'amount' => 0.00,
                'balance_before' => $lockedCustWallet->balance,
                'balance_after' => $lockedCustWallet->balance,
                'held_balance_before' => $custHeldBefore,
                'held_balance_after' => $custHeldAfter,
                'status' => WalletTransactionStatus::Completed,
                'reference_type' => Order::class,
                'reference_id' => $lockedOrder->id,
                'idempotency_key' => "order_hold_release_{$lockedOrder->id}_{$holdTx->id}",
                'description' => "Оплата заказа #{$lockedOrder->id} (списание из заморозки)",
                'metadata' => [
                    'order_id' => $lockedOrder->id,
                    'held_amount' => $heldAmount,
                ],
            ]);

            // 2. Calculate fee and payout
            // Двойная запись: payout-строка пишет валовую сумму (`amount` = gross, `held_balance_after` = до + gross),
            // фактически `pink_held` получает net; строка `PlatformFee` (−fee) замыкает цепочку, её `held_balance_after`
            // равно фактическому состоянию кошелька. Строки нельзя читать поодиночке — только цепочкой по
            // `idempotency_key` `order_payout_*` + `platform_fee_*`.
            $feePercent = $platformFeePercent ?? (float) PlatformSetting::get('platform_fee_percent', config('services.payments.platform_fee_percent', 10.0));
            $feeAmount = $this->roundFee($heldAmount, $feePercent);
            $netPayout = (float) bcsub((string) $heldAmount, (string) $feeAmount, 2);

            $windowMinutes = (int) PlatformSetting::get('order_dispute_window_minutes', 60);
            $heldUntil = now()->addMinutes($windowMinutes);

            // 3. Credit net payout into idol's pink_held_balance (held during dispute window)
            $idolPinkBalBefore = (float) $lockedIdolWallet->pink_balance;
            $idolPinkHeldBefore = (float) $lockedIdolWallet->pink_held_balance;
            $idolPinkHeldAfter = (float) bcadd((string) $idolPinkHeldBefore, (string) $netPayout, 2);

            $lockedIdolWallet->forceFill(['pink_held_balance' => $idolPinkHeldAfter])->save();

            $payoutTx = WalletTransaction::create([
                'wallet_id' => $lockedIdolWallet->id,
                'user_id' => $lockedOrder->idol_id,
                'type' => WalletTransactionType::OrderPayout,
                'currency' => 'pink',
                'amount' => $heldAmount,
                'balance_before' => $idolPinkBalBefore,
                'balance_after' => $idolPinkBalBefore,
                'held_balance_before' => $idolPinkHeldBefore,
                'held_balance_after' => (float) bcadd((string) $idolPinkHeldBefore, (string) $heldAmount, 2),
                'status' => WalletTransactionStatus::Pending,
                'reference_type' => Order::class,
                'reference_id' => $lockedOrder->id,
                'idempotency_key' => "order_payout_{$lockedOrder->id}_{$holdTx->id}",
                'description' => "Оплата за заказ #{$lockedOrder->id} (холд)",
                'metadata' => [
                    'order_id' => $lockedOrder->id,
                    'gross_amount' => $heldAmount,
                    'fee_percent' => $feePercent,
                    'fee_amount' => $feeAmount,
                    'net_payout' => $netPayout,
                    'held' => true,
                    'held_until' => $heldUntil->toISOString(),
                ],
            ]);

            $feeTx = null;
            if ($feeAmount > 0) {
                $feeTx = WalletTransaction::create([
                    'wallet_id' => $lockedIdolWallet->id,
                    'user_id' => $lockedOrder->idol_id,
                    'type' => WalletTransactionType::PlatformFee,
                    'currency' => 'pink',
                    'amount' => -$feeAmount,
                    'balance_before' => $idolPinkBalBefore,
                    'balance_after' => $idolPinkBalBefore,
                    'held_balance_before' => (float) bcadd((string) $idolPinkHeldBefore, (string) $heldAmount, 2),
                    'held_balance_after' => $idolPinkHeldAfter,
                    'status' => WalletTransactionStatus::Pending,
                    'reference_type' => Order::class,
                    'reference_id' => $lockedOrder->id,
                    'idempotency_key' => "platform_fee_{$lockedOrder->id}_{$holdTx->id}",
                    'description' => "Комиссия по заказу #{$lockedOrder->id}",
                    'metadata' => [
                        'order_id' => $lockedOrder->id,
                        'fee_percent' => $feePercent,
                        'gross_amount' => $heldAmount,
                        'held' => true,
                    ],
                ]);
            }

            DB::afterCommit(function () use ($lockedOrder, $heldUntil) {
                ReleaseIdolOrderHoldJob::dispatch($lockedOrder)->delay($heldUntil);
            });

            return [
                'payout' => $payoutTx,
                'fee' => $feeTx,
            ];
        });
    }

    /**
     * Release held payout funds to the idol's available balance after dispute window expires.
     */
    public function releaseIdolPayout(Order $order): ?WalletTransaction
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            if (! $lockedOrder) {
                return null;
            }

            // Must be completed
            if ($lockedOrder->status !== OrderStatus::Completed) {
                return null;
            }

            // Idempotency: already released
            if ($lockedOrder->payout_released_at !== null) {
                return null;
            }

            // Cannot release if there is an open dispute
            if ($lockedOrder->disputes()->where('status', 'open')->exists()) {
                return null;
            }

            // Выплата заморожена до конца окна спора: клиент должен иметь возможность оспорить заказ всё окно целиком.
            if (! $lockedOrder->completed_at) {
                return null;
            }

            $windowMinutes = (int) PlatformSetting::get('order_dispute_window_minutes', 60);
            $deadline = $lockedOrder->completed_at->copy()->addMinutes($windowMinutes);
            if (now()->lt($deadline)) {
                return null;
            }

            // Find the pending payout transaction
            $payoutTx = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $lockedOrder->id)
                ->where('type', WalletTransactionType::OrderPayout)
                ->where('status', WalletTransactionStatus::Pending)
                ->first();

            if (! $payoutTx) {
                $lockedOrder->forceFill(['payout_released_at' => now()])->save();

                return null;
            }

            $grossAmount = abs((float) $payoutTx->amount);
            $payoutMeta = $payoutTx->metadata ?? [];
            $feeAmount = (float) ($payoutMeta['fee_amount'] ?? 0.0);
            $netPayout = (float) ($payoutMeta['net_payout'] ?? bcsub((string) $grossAmount, (string) $feeAmount, 2));

            if ($netPayout <= 0 && $grossAmount <= 0) {
                $lockedOrder->forceFill(['payout_released_at' => now()])->save();

                return null;
            }

            if (! $lockedOrder->idol) {
                Log::warning("Idol user missing for order #{$lockedOrder->id}; locking payout release");
                $lockedOrder->forceFill(['payout_released_at' => now()])->save();

                return null;
            }

            $idolWallet = $this->getOrCreateWallet($lockedOrder->idol);
            $lockedIdolWallet = Wallet::where('id', $idolWallet->id)->lockForUpdate()->first();

            if (! $lockedIdolWallet->is_active) {
                Log::warning("Idol wallet #{$lockedIdolWallet->id} is deactivated; locking payout release for order #{$lockedOrder->id}");
                $lockedOrder->forceFill(['payout_released_at' => now()])->save();

                return null;
            }

            $pinkHeldBefore = (float) $lockedIdolWallet->pink_held_balance;
            $pinkHeldAfter = max(0.00, (float) bcsub((string) $pinkHeldBefore, (string) $netPayout, 2));
            $pinkBalBefore = (float) $lockedIdolWallet->pink_balance;
            $pinkBalAfter = (float) bcadd((string) $pinkBalBefore, (string) $netPayout, 2);

            $lockedIdolWallet->forceFill([
                'pink_balance' => $pinkBalAfter,
                'pink_held_balance' => $pinkHeldAfter,
            ])->save();

            $payoutMeta['held'] = false;
            $payoutMeta['released_at'] = now()->toISOString();

            // If there's a pending platform fee transaction for this order, complete it as well
            $feeTx = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $lockedOrder->id)
                ->where('type', WalletTransactionType::PlatformFee)
                ->where('status', WalletTransactionStatus::Pending)
                ->first();

            $payoutBalAfter = $feeTx
                ? (float) bcadd((string) $pinkBalBefore, (string) $grossAmount, 2)
                : $pinkBalAfter;

            $payoutTx->update([
                'status' => WalletTransactionStatus::Completed,
                'currency' => 'pink',
                'balance_before' => $pinkBalBefore,
                'balance_after' => $payoutBalAfter,
                'held_balance_before' => $pinkHeldBefore,
                'held_balance_after' => $pinkHeldAfter,
                'description' => "Оплата за заказ #{$lockedOrder->id}",
                'metadata' => $payoutMeta,
            ]);

            if ($feeTx) {
                $feeMeta = $feeTx->metadata ?? [];
                $feeMeta['held'] = false;
                $feeTx->update([
                    'status' => WalletTransactionStatus::Completed,
                    'currency' => 'pink',
                    'balance_before' => $payoutBalAfter,
                    'balance_after' => $pinkBalAfter,
                    'held_balance_before' => $pinkHeldAfter,
                    'held_balance_after' => $pinkHeldAfter,
                    'metadata' => $feeMeta,
                ]);
            }

            $lockedOrder->forceFill([
                'payout_released_at' => now(),
                'status' => OrderStatus::Closed,
            ])->save();

            $lockedOrder->logStatusChange(
                OrderStatus::Completed->value,
                OrderStatus::Closed->value,
                'system',
                null,
                'Автоматическое закрытие сделки по истечении 60 минут (ст. 720 ГК РФ)'
            );

            DB::afterCommit(function () use ($lockedOrder, $netPayout, $grossAmount) {
                try {
                    $lockedOrder->idol?->notify(new IdolPayoutReleasedNotification($lockedOrder, $netPayout));
                    $this->safeBroadcast(new NewNotification('private', $lockedOrder->idol_id));

                    // Автофискализация чека самозанятого по 422-ФЗ в ФНС («Мой налог»)
                    if ($lockedOrder->idol?->isSelfEmployedVerified() && $lockedOrder->idol->inn) {
                        try {
                            $gateway = app(\App\Contracts\MandarinGatewayInterface::class);
                            $receipt = $gateway->registerSelfEmployedReceipt(
                                $lockedOrder->idol->inn,
                                $grossAmount,
                                "Оказание услуг по заказу #{$lockedOrder->id}",
                                $lockedOrder->customer?->email,
                                "order_{$lockedOrder->id}"
                            );
                            Log::channel('payments')->info("Registered 422-FZ receipt for order #{$lockedOrder->id}", $receipt);
                        } catch (\Throwable $e) {
                            Log::channel('payments')->error("Failed to register 422-FZ receipt for order #{$lockedOrder->id}: {$e->getMessage()}");
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to dispatch IdolPayoutReleasedNotification for order #{$lockedOrder->id}: {$e->getMessage()}");
                }
            });

            return $payoutTx;
        });
    }

    /**
     * Refund held funds back to the customer's available balance upon cancellation/refund.
     */
    public function refundHold(Order $order, ?string $reason = null): ?WalletTransaction
    {
        try {
            return DB::transaction(function () use ($order, $reason) {
                $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
                if (! $lockedOrder) {
                    return null;
                }

                // Find latest active hold transaction
                $latestHold = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $lockedOrder->id)
                    ->where('type', WalletTransactionType::OrderHold)
                    ->where('status', WalletTransactionStatus::Completed)
                    ->latest('id')
                    ->first();

                if (! $latestHold) {
                    return null;
                }

                // Check if already refunded for this hold
                $latestRefund = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $lockedOrder->id)
                    ->where('type', WalletTransactionType::OrderRefund)
                    ->latest('id')
                    ->first();

                if ($latestRefund && $latestRefund->id > $latestHold->id) {
                    return $latestRefund;
                }

                $heldAmount = abs((float) $latestHold->amount);
                if ($heldAmount <= 0) {
                    return null;
                }

                // Инвариант (AGENTS.md §3.3): клиенту возвращается 100% стоимости услуг из холда.
                $netRefund = $heldAmount;

                $refundMetadata = [
                    'order_id' => $lockedOrder->id,
                    'reason' => $reason,
                    'gross_amount' => $heldAmount,
                    'net_refund' => $netRefund,
                ];

                // Check if payout occurred for this hold
                $payoutTx = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $lockedOrder->id)
                    ->where('type', WalletTransactionType::OrderPayout)
                    ->where('id', '>', $latestHold->id)
                    ->latest('id')
                    ->first();

                if (! $lockedOrder->customer) {
                    throw new \DomainException('Клиент заказа не найден.');
                }

                $customerWallet = $this->getOrCreateWallet($lockedOrder->customer);

                if ($payoutTx && $payoutTx->status !== WalletTransactionStatus::Cancelled) {
                    // Completed order refund: claw back funds from idol, return funds to customer
                    if (! $lockedOrder->idol) {
                        throw new \DomainException('Айдол заказа не найден.');
                    }

                    $idolWallet = $this->getOrCreateWallet($lockedOrder->idol);

                    // Lock wallets in ascending ID order to avoid deadlocks
                    $firstId = min($customerWallet->id, $idolWallet->id);
                    $secondId = max($customerWallet->id, $idolWallet->id);

                    $firstLocked = Wallet::where('id', $firstId)->lockForUpdate()->first();
                    $secondLocked = ($firstId !== $secondId)
                        ? Wallet::where('id', $secondId)->lockForUpdate()->first()
                        : $firstLocked;

                    $lockedCustomerWallet = $customerWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;
                    $lockedIdolWallet = $idolWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;

                    if (! $lockedCustomerWallet->is_active) {
                        throw new \DomainException('Кошелёк клиента деактивирован.');
                    }

                    $feeTx = WalletTransaction::where('reference_type', Order::class)
                        ->where('reference_id', $lockedOrder->id)
                        ->where('type', WalletTransactionType::PlatformFee)
                        ->where('id', '>', $latestHold->id)
                        ->first();

                    $grossAmount = $heldAmount;
                    $payoutMeta = $payoutTx->metadata ?? [];
                    $feeAmount = $feeTx ? abs((float) $feeTx->amount) : (float) ($payoutMeta['fee_amount'] ?? 0.0);
                    $netIdolDeduction = (float) ($payoutMeta['net_payout'] ?? bcsub((string) $grossAmount, (string) $feeAmount, 2));

                    $isStillHeld = ($payoutTx->status === WalletTransactionStatus::Pending);

                    $idolPinkBalBefore = (float) $lockedIdolWallet->pink_balance;
                    $idolPinkHeldBefore = (float) $lockedIdolWallet->pink_held_balance;

                    if ($isStillHeld) {
                        // Payout is still in pink_held_balance: deduct from pink_held_balance!
                        $idolPinkHeldAfter = max(0.00, (float) bcsub((string) $idolPinkHeldBefore, (string) $netIdolDeduction, 2));
                        $idolPinkBalAfter = $idolPinkBalBefore;
                        $lockedIdolWallet->forceFill(['pink_held_balance' => $idolPinkHeldAfter])->save();

                        // Cancel the pending payout transaction
                        $payoutTxMeta = $payoutTx->metadata ?? [];
                        $payoutTxMeta['refunded'] = true;
                        $payoutTxMeta['held'] = false;
                        $payoutTx->update([
                            'status' => WalletTransactionStatus::Cancelled,
                            'metadata' => $payoutTxMeta,
                        ]);

                        // Also cancel the pending fee transaction
                        if ($feeTx) {
                            $feeMeta = $feeTx->metadata ?? [];
                            $feeMeta['refunded'] = true;
                            $feeMeta['held'] = false;
                            $feeTx->update([
                                'status' => WalletTransactionStatus::Cancelled,
                                'metadata' => $feeMeta,
                            ]);
                        }

                        // Record completed ledger entry for idol held balance cancellation
                        WalletTransaction::create([
                            'wallet_id' => $lockedIdolWallet->id,
                            'user_id' => $lockedOrder->idol_id,
                            'type' => WalletTransactionType::OrderHoldCancelled,
                            'currency' => 'pink',
                            'amount' => 0.00,
                            'balance_before' => $idolPinkBalBefore,
                            'balance_after' => $idolPinkBalAfter,
                            'held_balance_before' => $idolPinkHeldBefore,
                            'held_balance_after' => $idolPinkHeldAfter,
                            'status' => WalletTransactionStatus::Completed,
                            'reference_type' => Order::class,
                            'reference_id' => $lockedOrder->id,
                            'idempotency_key' => "order_hold_cancelled_{$lockedOrder->id}_{$latestHold->id}",
                            'description' => "Отмена холда по спору заказа #{$lockedOrder->id}",
                            'metadata' => [
                                'order_id' => $lockedOrder->id,
                                'held_amount' => $netIdolDeduction,
                                'gross_amount' => $grossAmount,
                                'fee_amount' => $feeAmount,
                                'from_held' => true,
                                'reason' => $reason,
                            ],
                        ]);
                    } else {
                        // Сделка уже закрыта по ст. 720 ГК РФ, средства переведены исполнителю.
                        // Овердрафты запрещены. Возврат через платформу после закрытия невозможен.
                        throw new \DomainException('Сделка по заказу уже закрыта (ст. 720 ГК РФ). Возврат средств через платформу невозможен.');
                    }

                    // Refund customer
                    $custBalBefore = $lockedCustomerWallet->balance;
                    $custBalAfter = (float) bcadd((string) $custBalBefore, (string) $netRefund, 2);
                    $lockedCustomerWallet->forceFill(['balance' => $custBalAfter])->save();

                    $lockedOrder->forceFill(['payout_released_at' => null])->save();

                    return WalletTransaction::create([
                        'wallet_id' => $lockedCustomerWallet->id,
                        'user_id' => $lockedOrder->customer_id,
                        'type' => WalletTransactionType::OrderRefund,
                        'currency' => 'blue',
                        'amount' => $netRefund,
                        'balance_before' => $custBalBefore,
                        'balance_after' => $custBalAfter,
                        'held_balance_before' => $lockedCustomerWallet->held_balance,
                        'held_balance_after' => $lockedCustomerWallet->held_balance,
                        'status' => WalletTransactionStatus::Completed,
                        'reference_type' => Order::class,
                        'reference_id' => $lockedOrder->id,
                        'idempotency_key' => "order_refund_{$lockedOrder->id}_{$latestHold->id}",
                        'description' => "Возврат средств по заказу #{$lockedOrder->id}".($reason ? ": {$reason}" : ''),
                        'metadata' => array_merge($refundMetadata, [
                            'from_completed' => true,
                        ]),
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
                $balAfter = (float) bcadd((string) $balBefore, (string) $netRefund, 2);

                $lockedWallet->forceFill([
                    'balance' => $balAfter,
                    'held_balance' => $heldAfter,
                ])->save();

                return WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'user_id' => $lockedOrder->customer_id,
                    'type' => WalletTransactionType::OrderRefund,
                    'currency' => 'blue',
                    'amount' => $netRefund,
                    'balance_before' => $balBefore,
                    'balance_after' => $balAfter,
                    'held_balance_before' => $heldBefore,
                    'held_balance_after' => $heldAfter,
                    'status' => WalletTransactionStatus::Completed,
                    'reference_type' => Order::class,
                    'reference_id' => $lockedOrder->id,
                    'idempotency_key' => "order_refund_{$lockedOrder->id}_{$latestHold->id}",
                    'description' => "Возврат средств по заказу #{$lockedOrder->id}".($reason ? ": {$reason}" : ''),
                    'metadata' => $refundMetadata,
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $existingRefund = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', WalletTransactionType::OrderRefund)
                ->latest('id')
                ->first();

            if ($existingRefund) {
                return $existingRefund;
            }

            throw $e;
        }
    }

    /**
     * Purchase a content pack with wallet transactions or mock mode.
     */
    public function purchaseContentPack(
        User $buyer,
        ContentPack $pack,
        ?float $platformFeePercent = null,
        bool $feeCoveredPlatform = false,
        bool $feeCoveredWithdrawal = false
    ): ContentPackPurchase {
        if ($buyer->id === $pack->user_id) {
            throw new \DomainException('Нельзя покупать собственный контент-пак');
        }

        if (method_exists($buyer, 'isActiveBanned') && $buyer->isActiveBanned()) {
            throw new \DomainException('Операция недоступна для заблокированного аккаунта.');
        }

        if (! $pack->user) {
            throw new \DomainException('Автор контент-пака недоступен.');
        }

        if ($pack->user && method_exists($pack->user, 'trashed') && $pack->user->trashed()) {
            throw new \DomainException('Автор контент-пака недоступен.');
        }

        if (method_exists($pack->user, 'isActiveBanned') && $pack->user->isActiveBanned()) {
            throw new \DomainException('Автор контент-пака заблокирован.');
        }

        if ($pack->status !== 'published') {
            throw new \DomainException('Контент-пак недоступен для покупки.');
        }

        if ($platformFeePercent !== null && ($platformFeePercent < 0 || $platformFeePercent > 100)) {
            throw new \InvalidArgumentException('Процент комиссии платформы должен быть в диапазоне от 0 до 100.');
        }

        $isMock = app()->environment('local', 'testing') || (config('services.payments.mock_purchases', false) && ! app()->environment('production'));
        $price = (float) ($pack->price ?? 0);

        try {
            return DB::transaction(function () use ($buyer, $pack, $price, $isMock, $platformFeePercent, $feeCoveredPlatform, $feeCoveredWithdrawal) {
                $alreadyPurchased = ContentPackPurchase::where('content_pack_id', $pack->id)
                    ->where('user_id', $buyer->id)
                    ->lockForUpdate()
                    ->exists();
                if ($alreadyPurchased) {
                    throw new \DomainException('Контент-пак уже приобретён.');
                }

                $amountCharged = $price;
                if ($price > 0) {
                    $coverage = $this->calculateFeeCoverage($price, $feeCoveredPlatform, $feeCoveredWithdrawal, $platformFeePercent);
                    $amountCharged = $coverage['amount_charged'];
                    $feePercent = $coverage['platform_fee_percent'];
                    $feeAmount = $coverage['platform_fee'];
                    $netPayout = $coverage['net_payout'];

                    $seller = $pack->user;
                    $buyerWallet = $this->getOrCreateWallet($buyer);

                    if ($isMock && ! $buyerWallet->hasSufficientBalance($amountCharged)) {
                        $needed = (float) bcsub((string) $amountCharged, (string) $buyerWallet->balance, 2);
                        $this->deposit($buyer, $needed, 'Тестовое пополнение для покупки пака', null, [], false);
                        $buyerWallet->refresh();
                    }

                    $sellerWallet = $this->getOrCreateWallet($seller);

                    // Lock wallets in ascending ID order to avoid deadlocks
                    $firstId = min($buyerWallet->id, $sellerWallet->id);
                    $secondId = max($buyerWallet->id, $sellerWallet->id);

                    $firstLocked = Wallet::where('id', $firstId)->lockForUpdate()->first();
                    $secondLocked = ($firstId !== $secondId)
                        ? Wallet::where('id', $secondId)->lockForUpdate()->first()
                        : $firstLocked;

                    $lockedBuyerWallet = $buyerWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;
                    $lockedSellerWallet = $sellerWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;

                    if (! $lockedBuyerWallet->is_active || ! $lockedSellerWallet->is_active) {
                        throw new \DomainException('Один из кошельков деактивирован.');
                    }

                    if (! $lockedBuyerWallet->hasSufficientBalance($amountCharged)) {
                        throw new InsufficientFundsException(
                            "Недостаточно средств для покупки пака ({$amountCharged} руб.)"
                        );
                    }

                    // 1. Deduct from buyer
                    $buyerBalBefore = $lockedBuyerWallet->balance;
                    $buyerBalAfter = (float) bcsub((string) $buyerBalBefore, (string) $amountCharged, 2);
                    $lockedBuyerWallet->forceFill(['balance' => $buyerBalAfter])->save();

                    $buyerTx = WalletTransaction::create([
                        'wallet_id' => $lockedBuyerWallet->id,
                        'user_id' => $buyer->id,
                        'type' => WalletTransactionType::PackPurchase,
                        'currency' => 'blue',
                        'amount' => -$amountCharged,
                        'balance_before' => $buyerBalBefore,
                        'balance_after' => $buyerBalAfter,
                        'held_balance_before' => $lockedBuyerWallet->held_balance,
                        'held_balance_after' => $lockedBuyerWallet->held_balance,
                        'status' => WalletTransactionStatus::Completed,
                        'reference_type' => ContentPack::class,
                        'reference_id' => $pack->id,
                        'idempotency_key' => "pack_purchase_{$buyer->id}_{$pack->id}",
                        'description' => "Покупка контент-пака «{$pack->title}»",
                        'metadata' => [
                            'pack_id' => $pack->id,
                            'price' => $price,
                            'amount_charged' => $amountCharged,
                            'fee_covered_platform' => $feeCoveredPlatform,
                            'fee_covered_withdrawal' => $feeCoveredWithdrawal,
                        ],
                    ]);

                    // 2. Credit seller idol gross in pink coins, then deduct fee
                    $sellerPinkBalBefore = (float) $lockedSellerWallet->pink_balance;
                    $sellerPinkBalAfterGross = (float) bcadd((string) $sellerPinkBalBefore, (string) $amountCharged, 2);

                    WalletTransaction::create([
                        'wallet_id' => $lockedSellerWallet->id,
                        'user_id' => $seller->id,
                        'type' => WalletTransactionType::PackSale,
                        'currency' => 'pink',
                        'amount' => $amountCharged,
                        'balance_before' => $sellerPinkBalBefore,
                        'balance_after' => $sellerPinkBalAfterGross,
                        'held_balance_before' => (float) $lockedSellerWallet->pink_held_balance,
                        'held_balance_after' => (float) $lockedSellerWallet->pink_held_balance,
                        'status' => WalletTransactionStatus::Completed,
                        'reference_type' => ContentPack::class,
                        'reference_id' => $pack->id,
                        'idempotency_key' => "pack_sale_{$buyer->id}_{$pack->id}",
                        'description' => "Продажа контент-пака «{$pack->title}»",
                        'metadata' => [
                            'pack_id' => $pack->id,
                            'buyer_id' => $buyer->id,
                            'price' => $price,
                            'gross_amount' => $amountCharged,
                            'fee_percent' => $feePercent,
                            'fee_amount' => $feeAmount,
                            'net_payout' => $netPayout,
                            'fee_covered_platform' => $feeCoveredPlatform,
                            'fee_covered_withdrawal' => $feeCoveredWithdrawal,
                        ],
                    ]);

                    if ($feeAmount > 0) {
                        $sellerPinkBalAfterFee = (float) bcsub((string) $sellerPinkBalAfterGross, (string) $feeAmount, 2);
                        $lockedSellerWallet->forceFill(['pink_balance' => $sellerPinkBalAfterFee])->save();

                        WalletTransaction::create([
                            'wallet_id' => $lockedSellerWallet->id,
                            'user_id' => $seller->id,
                            'type' => WalletTransactionType::PlatformFee,
                            'currency' => 'pink',
                            'amount' => -$feeAmount,
                            'balance_before' => $sellerPinkBalAfterGross,
                            'balance_after' => $sellerPinkBalAfterFee,
                            'held_balance_before' => (float) $lockedSellerWallet->pink_held_balance,
                            'held_balance_after' => (float) $lockedSellerWallet->pink_held_balance,
                            'status' => WalletTransactionStatus::Completed,
                            'reference_type' => ContentPack::class,
                            'reference_id' => $pack->id,
                            'idempotency_key' => "pack_fee_{$buyer->id}_{$pack->id}",
                            'description' => "Комиссия за контент-пак «{$pack->title}»",
                            'metadata' => [
                                'pack_id' => $pack->id,
                                'fee_percent' => $feePercent,
                                'fee_covered_platform' => $feeCoveredPlatform,
                                'fee_covered_withdrawal' => $feeCoveredWithdrawal,
                            ],
                        ]);
                    } else {
                        $lockedSellerWallet->forceFill(['pink_balance' => $sellerPinkBalAfterGross])->save();
                    }
                }

                $purchase = ContentPackPurchase::firstOrCreate(
                    [
                        'content_pack_id' => $pack->id,
                        'user_id' => $buyer->id,
                    ],
                    [
                        'price_paid' => $price,
                        'amount_charged' => $amountCharged,
                        'fee_covered_platform' => $feeCoveredPlatform,
                        'fee_covered_withdrawal' => $feeCoveredWithdrawal,
                        'purchased_at' => now(),
                    ]
                );

                if ($purchase->wasRecentlyCreated) {
                    DB::afterCommit(fn () => ContentPackPurchasedEvent::dispatch($purchase));
                }

                return $purchase;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $existing = ContentPackPurchase::where('content_pack_id', $pack->id)
                ->where('user_id', $buyer->id)
                ->first();
            if ($existing) {
                return $existing;
            }
            throw new \DomainException('Контент-пак уже приобретён.');
        }
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
        $amount = round($amount, 2);

        if (! $user->is_idol) {
            throw new \DomainException('Вывод средств разрешён только айдолам.');
        }

        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            throw new \DomainException('Операция недоступна для заблокированного аккаунта.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма вывода должна быть больше нуля.');
        }

        $wallet = $this->getOrCreateWallet($user);

        if ($idempotencyKey) {
            $existing = $this->findExistingIdempotentTransaction($wallet, $idempotencyKey, WalletTransactionType::Withdrawal, $amount);
            if ($existing) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($user, $amount, $idempotencyKey, $description, $metadata, $wallet) {
                $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

                if (! $lockedWallet->is_active) {
                    throw new \DomainException('Кошелёк деактивирован.');
                }

                if ($idempotencyKey) {
                    $existing = $this->findExistingIdempotentTransaction($lockedWallet, $idempotencyKey, WalletTransactionType::Withdrawal, $amount);
                    if ($existing) {
                        return $existing;
                    }
                }

                $available = (float) $lockedWallet->available_for_payout;
                if ($amount > $available) {
                    throw new InsufficientFundsException(
                        "Сумма вывода ({$amount} ₽) превышает доступный лимит ({$available} ₽) с учётом долговых обязательств."
                    );
                }

                if (! $lockedWallet->hasSufficientPinkBalance($amount)) {
                    throw new InsufficientFundsException(
                        "Недостаточно розовых монет для вывода. Доступно: {$lockedWallet->pink_balance}, запрошено: {$amount}"
                    );
                }

                $feePercent = (float) PlatformSetting::get('withdrawal_fee_percent', config('services.payments.withdrawal_fee_percent', 0.0));
                $feeAmount = $this->roundFee($amount, $feePercent);
                $payoutAmount = (float) bcsub((string) $amount, (string) $feeAmount, 2);

                $metadata = $metadata ?? [];
                $metadata = array_merge([
                    'payout_method' => 'sbp',
                    'fee_percent' => $feePercent,
                    'fee_amount' => $feeAmount,
                    'payout_amount' => $payoutAmount,
                ], $metadata);

                $pinkBalBefore = (float) $lockedWallet->pink_balance;
                $pinkBalAfter = (float) bcsub((string) $pinkBalBefore, (string) $amount, 2);

                $lockedWallet->forceFill(['pink_balance' => $pinkBalAfter])->save();
                $this->checkOverdraftAlert($lockedWallet, $pinkBalAfter);

                return WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'user_id' => $user->id,
                    'type' => WalletTransactionType::Withdrawal,
                    'currency' => 'pink',
                    'amount' => -$amount,
                    'balance_before' => $pinkBalBefore,
                    'balance_after' => $pinkBalAfter,
                    'held_balance_before' => (float) $lockedWallet->pink_held_balance,
                    'held_balance_after' => (float) $lockedWallet->pink_held_balance,
                    'status' => WalletTransactionStatus::Completed,
                    'idempotency_key' => $idempotencyKey,
                    'description' => $description ?? 'Вывод средств со счёта',
                    'metadata' => $metadata,
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return $this->handleIdempotencyConflict(
                $e,
                $wallet,
                $idempotencyKey,
                WalletTransactionType::Withdrawal,
                $amount
            );
        }
    }

    /**
     * Convert an idol's pink coins into spendable blue coins (1:1 ratio).
     * Blue coins cannot be converted back to pink coins.
     */
    public function convertPinkToBlue(User $user, float $amount, ?string $idempotencyKey = null): array
    {
        if (! $user->is_idol) {
            throw new \DomainException('Конвертация доступна только айдолам.');
        }

        if (method_exists($user, 'isActiveBanned') && $user->isActiveBanned()) {
            throw new \DomainException('Операция недоступна для заблокированного аккаунта.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма конвертации должна быть больше нуля.');
        }

        $wallet = $this->getOrCreateWallet($user);

        $pinkTxKey = $idempotencyKey ? 'p2b_'.substr(hash('sha256', "p_{$idempotencyKey}"), 0, 56) : null;
        $blueTxKey = $idempotencyKey ? 'b2p_'.substr(hash('sha256', "b_{$idempotencyKey}"), 0, 56) : null;

        if ($pinkTxKey) {
            $existingPink = WalletTransaction::where('idempotency_key', $pinkTxKey)->first();
            if ($existingPink) {
                if ($existingPink->wallet_id !== $wallet->id) {
                    throw new \DomainException('Ключ идемпотентности уже использован для другой операции.');
                }
                if (abs((float) $existingPink->amount) !== (float) $amount) {
                    throw new \DomainException('Ключ идемпотентности уже использован с другими параметрами.');
                }
                $existingBlue = WalletTransaction::where('idempotency_key', $blueTxKey)->first();
                $currentWallet = $wallet->fresh();

                return [
                    'pink_transaction' => $existingPink,
                    'blue_transaction' => $existingBlue,
                    'pink_balance' => (float) $currentWallet->pink_balance,
                    'balance' => (float) $currentWallet->balance,
                ];
            }
        }

        try {
            return DB::transaction(function () use ($user, $amount, $wallet, $pinkTxKey, $blueTxKey) {
                $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

                if (! $lockedWallet->is_active) {
                    throw new \DomainException('Кошелёк деактивирован.');
                }

                if ($pinkTxKey) {
                    $existingPink = WalletTransaction::where('idempotency_key', $pinkTxKey)->first();
                    if ($existingPink) {
                        if ($existingPink->wallet_id !== $lockedWallet->id) {
                            throw new \DomainException('Ключ идемпотентности уже использован для другой операции.');
                        }
                        if (abs((float) $existingPink->amount) !== (float) $amount) {
                            throw new \DomainException('Ключ идемпотентности уже использован с другими параметрами.');
                        }
                        $existingBlue = WalletTransaction::where('idempotency_key', $blueTxKey)->first();

                        return [
                            'pink_transaction' => $existingPink,
                            'blue_transaction' => $existingBlue,
                            'pink_balance' => (float) $lockedWallet->pink_balance,
                            'balance' => (float) $lockedWallet->balance,
                        ];
                    }
                }

                if (! $lockedWallet->hasSufficientPinkBalance($amount)) {
                    throw new InsufficientFundsException(
                        "Недостаточно розовых монет для конвертации. Доступно: {$lockedWallet->pink_balance}, запрошено: {$amount}"
                    );
                }

                $pinkBalBefore = (float) $lockedWallet->pink_balance;
                $pinkBalAfter = (float) bcsub((string) $pinkBalBefore, (string) $amount, 2);

                $blueBalBefore = (float) $lockedWallet->balance;
                $blueBalAfter = (float) bcadd((string) $blueBalBefore, (string) $amount, 2);

                $lockedWallet->forceFill([
                    'pink_balance' => $pinkBalAfter,
                    'balance' => $blueBalAfter,
                ])->save();

                $pinkTx = WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'user_id' => $user->id,
                    'type' => WalletTransactionType::PinkToBlueConversion,
                    'currency' => 'pink',
                    'amount' => -$amount,
                    'balance_before' => $pinkBalBefore,
                    'balance_after' => $pinkBalAfter,
                    'held_balance_before' => (float) $lockedWallet->pink_held_balance,
                    'held_balance_after' => (float) $lockedWallet->pink_held_balance,
                    'status' => WalletTransactionStatus::Completed,
                    'idempotency_key' => $pinkTxKey,
                    'description' => "Перевод {$amount} розовых монет в синие монеты",
                    'metadata' => [
                        'amount' => $amount,
                        'direction' => 'pink_to_blue',
                    ],
                ]);

                $blueTx = WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'user_id' => $user->id,
                    'type' => WalletTransactionType::BlueFromPinkConversion,
                    'currency' => 'blue',
                    'amount' => $amount,
                    'balance_before' => $blueBalBefore,
                    'balance_after' => $blueBalAfter,
                    'held_balance_before' => (float) $lockedWallet->held_balance,
                    'held_balance_after' => (float) $lockedWallet->held_balance,
                    'status' => WalletTransactionStatus::Completed,
                    'idempotency_key' => $blueTxKey,
                    'description' => "Зачисление {$amount} синих монет из розовых",
                    'metadata' => [
                        'amount' => $amount,
                        'direction' => 'blue_from_pink',
                        'pink_transaction_id' => $pinkTx->id,
                    ],
                ]);

                return [
                    'pink_transaction' => $pinkTx,
                    'blue_transaction' => $blueTx,
                    'pink_balance' => $pinkBalAfter,
                    'balance' => $blueBalAfter,
                ];
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            if ($pinkTxKey) {
                $existingPink = WalletTransaction::where('idempotency_key', $pinkTxKey)->first();
                if ($existingPink) {
                    if ($existingPink->wallet_id !== $wallet->id) {
                        throw new \DomainException('Ключ идемпотентности уже использован для другой операции.');
                    }
                    if (abs((float) $existingPink->amount) !== (float) $amount) {
                        throw new \DomainException('Ключ идемпотентности уже использован с другими параметрами.');
                    }
                    $existingBlue = WalletTransaction::where('idempotency_key', $blueTxKey)->first();
                    $freshWallet = $wallet->fresh();

                    return [
                        'pink_transaction' => $existingPink,
                        'blue_transaction' => $existingBlue,
                        'pink_balance' => (float) $freshWallet->pink_balance,
                        'balance' => (float) $freshWallet->balance,
                    ];
                }
            }

            throw $e;
        }
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
        ?string $type = null,
        ?string $idempotencyKey = null
    ): WalletTransaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма операции должна быть больше нуля.');
        }

        if (! in_array($direction, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException('Некорректное направление операции.');
        }

        if ($direction === 'debit' && $type === 'deposit') {
            throw new \InvalidArgumentException('Списание не может иметь тип «deposit».');
        }

        if ($direction === 'credit' && $type === 'withdrawal') {
            throw new \InvalidArgumentException('Начисление не может иметь тип «withdrawal».');
        }

        $wallet = $this->getOrCreateWallet($user);

        $txType = match ($type) {
            'deposit' => WalletTransactionType::Deposit,
            'withdrawal' => WalletTransactionType::Withdrawal,
            default => WalletTransactionType::AdminAdjustment,
        };

        if ($idempotencyKey) {
            $existing = $this->findExistingIdempotentTransaction($wallet, $idempotencyKey, $txType, $amount, $direction);
            if ($existing) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($user, $amount, $direction, $description, $adminId, $wallet, $txType, $idempotencyKey) {
                $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

                if (! $lockedWallet->is_active) {
                    throw new \DomainException('Кошелёк пользователя деактивирован.');
                }

                if ($idempotencyKey) {
                    $existing = $this->findExistingIdempotentTransaction($lockedWallet, $idempotencyKey, $txType, $amount, $direction);
                    if ($existing) {
                        return $existing;
                    }
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
                    $this->checkOverdraftAlert($lockedWallet, $balanceAfter);
                } else {
                    $signedAmount = $amount;
                    $balanceAfter = (float) bcadd((string) $balanceBefore, (string) $amount, 2);
                }

                $lockedWallet->forceFill(['balance' => $balanceAfter])->save();

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
                    'idempotency_key' => $idempotencyKey,
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
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return $this->handleIdempotencyConflict(
                $e,
                $wallet,
                $idempotencyKey,
                $txType,
                $amount,
                $direction
            );
        }
    }

    /**
     * Check and retrieve an existing transaction by idempotency key with wallet isolation and parameter validation.
     *
     * @throws \DomainException
     */
    protected function findExistingIdempotentTransaction(
        Wallet $wallet,
        string $idempotencyKey,
        WalletTransactionType $expectedType,
        float $amount,
        ?string $direction = null
    ): ?WalletTransaction {
        $existing = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            $this->validateIdempotentTransaction($existing, $expectedType, $amount, $direction);

            return $existing;
        }

        // Check if the key already exists globally in the database under another wallet
        $globalExisting = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($globalExisting && $globalExisting->wallet_id !== $wallet->id) {
            throw new \DomainException('Ключ идемпотентности уже использован для другой операции.');
        }

        return null;
    }

    /**
     * Handle unique constraint violation on idempotency key.
     *
     * @throws \DomainException
     */
    protected function handleIdempotencyConflict(
        \Illuminate\Database\UniqueConstraintViolationException $e,
        Wallet $wallet,
        ?string $idempotencyKey,
        WalletTransactionType $expectedType,
        float $amount,
        ?string $direction = null
    ): WalletTransaction {
        if ($idempotencyKey) {
            $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->wallet_id !== $wallet->id) {
                    throw new \DomainException('Ключ идемпотентности уже использован для другой операции.');
                }
                $this->validateIdempotentTransaction($existing, $expectedType, $amount, $direction);

                return $existing;
            }
        }

        throw $e;
    }

    /**
     * Validate that existing idempotent transaction matches the expected parameters.
     *
     * @throws \DomainException
     */
    protected function validateIdempotentTransaction(
        WalletTransaction $existing,
        WalletTransactionType $expectedType,
        float $amount,
        ?string $direction = null
    ): void {
        // 1. Verify transaction type
        if ($existing->type !== $expectedType && $existing->type?->value !== $expectedType->value) {
            throw new \DomainException('Ключ идемпотентности уже использован с другими параметрами.');
        }

        // 2. Verify amount
        $existingAmount = number_format(abs((float) $existing->amount), 2, '.', '');
        $expectedAmount = number_format(abs($amount), 2, '.', '');
        $amountMatches = bccomp($existingAmount, $expectedAmount, 2) === 0;

        // For deposits with platform fee, gross_amount in metadata holds the original input amount
        if (! $amountMatches && isset($existing->metadata['gross_amount'])) {
            $grossAmount = number_format(abs((float) $existing->metadata['gross_amount']), 2, '.', '');
            if (bccomp($grossAmount, $expectedAmount, 2) === 0) {
                $amountMatches = true;
            }
        }

        // For withdrawals with platform fee, requested_amount in metadata holds the original requested amount
        if (! $amountMatches && isset($existing->metadata['requested_amount'])) {
            $requestedAmount = number_format(abs((float) $existing->metadata['requested_amount']), 2, '.', '');
            if (bccomp($requestedAmount, $expectedAmount, 2) === 0) {
                $amountMatches = true;
            }
        }

        if (! $amountMatches) {
            throw new \DomainException('Ключ идемпотентности уже использован с другими параметрами.');
        }

        // 3. For adminAdjust or direction-sensitive operations, verify direction if provided
        if ($direction !== null) {
            $isCredit = (float) $existing->amount > 0;
            $isDebit = (float) $existing->amount < 0;
            if ($direction === 'credit' && ! $isCredit) {
                throw new \DomainException('Ключ идемпотентности уже использован с другими параметрами.');
            }
            if ($direction === 'debit' && ! $isDebit) {
                throw new \DomainException('Ключ идемпотентности уже использован с другими параметрами.');
            }
        }
    }

    /**
     * Check if the balance after a transaction reached the critical overdraft threshold, and log an alert if so.
     */
    public function checkOverdraftAlert(Wallet $wallet, float $balanceAfter): void
    {
        if ($balanceAfter <= self::CRITICAL_OVERDRAFT_THRESHOLD) {
            Log::alert("Wallet #{$wallet->id} for user #{$wallet->user_id} reached critical negative balance: {$balanceAfter} RUB");
        }
    }

    /**
     * Округление суммы комиссии: half-up (PHP_ROUND_HALF_UP) до 2 знаков.
     * Остаток в копейку достаётся платформе: выплата = сумма − комиссия.
     */
    public function roundFee(float $amount, float $feePercent): float
    {
        if ($feePercent <= 0 || $amount <= 0) {
            return 0.0;
        }

        return round((float) bcdiv(bcmul((string) $amount, (string) $feePercent, 4), '100', 4), 2, PHP_ROUND_HALF_UP);
    }
}
