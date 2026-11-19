<?php

namespace App\Services;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Events\NewNotification;
use App\Exceptions\InsufficientFundsException;
use App\Models\Donation;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\DonationReceivedNotification;
use App\Traits\SafeBroadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonationService
{
    use SafeBroadcast;

    public function __construct(
        protected WalletService $walletService
    ) {}

    public function send(
        User $donor,
        User $idol,
        float $amount,
        bool $feeCoveredByDonor = false,
        ?string $message = null,
        ?string $idempotencyKey = null,
        ?float $platformFeePercent = null,
        bool $feeCoveredWithdrawal = false,
        ?float $withdrawalFeePercent = null
    ): Donation {
        if (! $idol->is_idol) {
            throw new \DomainException('Донаты можно отправлять только айдолам.');
        }

        if ($donor->id === $idol->id) {
            throw new \DomainException('Нельзя отправлять донат самому себе.');
        }

        if (method_exists($donor, 'isActiveBanned') && $donor->isActiveBanned()) {
            throw new \DomainException('Операция недоступна для заблокированного аккаунта.');
        }

        if (method_exists($idol, 'isActiveBanned') && $idol->isActiveBanned()) {
            throw new \DomainException('Получатель заблокирован.');
        }

        $minAmount = (float) PlatformSetting::get('donation_min_amount', 1.0);
        $maxAmount = (float) PlatformSetting::get('donation_max_amount', 15000.0);

        if (bccomp((string) $amount, (string) $minAmount, 2) < 0) {
            throw new \DomainException("Минимальная сумма доната составляет {$minAmount} монет.");
        }

        if (bccomp((string) $amount, (string) $maxAmount, 2) > 0) {
            throw new \DomainException("Максимальная сумма доната составляет {$maxAmount} монет.");
        }

        $dailyLimit = (int) PlatformSetting::get('donation_daily_limit', 10);
        $todayCount = Donation::where('donor_id', $donor->id)
            ->where('idol_id', $idol->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($todayCount >= $dailyLimit) {
            throw new \DomainException("Превышен дневной лимит донатов ({$dailyLimit} в день на одного айдола).");
        }

        $coverage = $this->walletService->calculateFeeCoverage(
            $amount,
            $feeCoveredByDonor,
            $feeCoveredWithdrawal,
            $platformFeePercent,
            $withdrawalFeePercent
        );
        $amountCharged = $coverage['amount_charged'];
        $feeAmount = $coverage['platform_fee'];
        $netPayout = $coverage['net_payout'];
        $withdrawalFee = (float) bcsub((string) $netPayout, (string) $coverage['expected_withdrawal_payout'], 2);

        $effectiveKey = $idempotencyKey ?: ('donation_'.$donor->id.'_'.$idol->id.'_'.Str::uuid());

        return DB::transaction(function () use (
            $donor,
            $idol,
            $amountCharged,
            $netPayout,
            $feeAmount,
            $withdrawalFee,
            $feeCoveredByDonor,
            $feeCoveredWithdrawal,
            $message,
            $effectiveKey
        ) {
            $existing = Donation::where('idempotency_key', $effectiveKey)->first();
            if ($existing) {
                return $existing;
            }

            $donorWallet = $this->walletService->getOrCreateWallet($donor);
            $idolWallet = $this->walletService->getOrCreateWallet($idol);

            $firstId = min($donorWallet->id, $idolWallet->id);
            $secondId = max($donorWallet->id, $idolWallet->id);

            $firstLocked = Wallet::where('id', $firstId)->lockForUpdate()->first();
            $secondLocked = ($firstId !== $secondId)
                ? Wallet::where('id', $secondId)->lockForUpdate()->first()
                : $firstLocked;

            $lockedDonorWallet = $donorWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;
            $lockedIdolWallet = $idolWallet->id === $firstLocked->id ? $firstLocked : $secondLocked;

            if (! $lockedDonorWallet->is_active || ! $lockedIdolWallet->is_active) {
                throw new \DomainException('Один из кошельков деактивирован.');
            }

            if (! $lockedDonorWallet->hasSufficientBalance($amountCharged)) {
                throw new InsufficientFundsException(
                    "Недостаточно синих монет для отправки доната ({$amountCharged} монет)."
                );
            }

            // 1. Debit donor (blue balance)
            $donorBalBefore = (float) $lockedDonorWallet->balance;
            $donorBalAfter = (float) bcsub((string) $donorBalBefore, (string) $amountCharged, 2);
            $lockedDonorWallet->forceFill(['balance' => $donorBalAfter])->save();

            // 2. Credit idol (pink balance)
            $idolPinkBalBefore = (float) $lockedIdolWallet->pink_balance;
            $idolPinkBalAfter = (float) bcadd((string) $idolPinkBalBefore, (string) $netPayout, 2);
            $lockedIdolWallet->forceFill(['pink_balance' => $idolPinkBalAfter])->save();

            // 3. Create Donation record
            $donation = Donation::create([
                'donor_id' => $donor->id,
                'idol_id' => $idol->id,
                'amount' => $netPayout,
                'amount_charged' => $amountCharged,
                'platform_fee' => $feeAmount,
                'withdrawal_fee' => $feeCoveredWithdrawal ? $withdrawalFee : 0.00,
                'fee_covered_by_donor' => $feeCoveredByDonor,
                'fee_covered_withdrawal' => $feeCoveredWithdrawal,
                'message' => $message,
                'idempotency_key' => $effectiveKey,
            ]);

            // 4. Create donor transaction
            WalletTransaction::create([
                'wallet_id' => $lockedDonorWallet->id,
                'user_id' => $donor->id,
                'type' => WalletTransactionType::DonationSent,
                'currency' => 'blue',
                'amount' => -$amountCharged,
                'balance_before' => $donorBalBefore,
                'balance_after' => $donorBalAfter,
                'held_balance_before' => (float) $lockedDonorWallet->held_balance,
                'held_balance_after' => (float) $lockedDonorWallet->held_balance,
                'status' => WalletTransactionStatus::Completed,
                'reference_type' => Donation::class,
                'reference_id' => $donation->id,
                'idempotency_key' => "donation_sent_{$donation->id}",
                'description' => "Донат для «{$idol->name}»",
                'metadata' => [
                    'donation_id' => $donation->id,
                    'idol_id' => $idol->id,
                    'net_payout' => $netPayout,
                    'platform_fee' => $feeAmount,
                    'withdrawal_fee' => $withdrawalFee,
                    'fee_covered_by_donor' => $feeCoveredByDonor,
                    'fee_covered_withdrawal' => $feeCoveredWithdrawal,
                    'message' => $message,
                ],
            ]);

            // 5. Create idol transaction
            WalletTransaction::create([
                'wallet_id' => $lockedIdolWallet->id,
                'user_id' => $idol->id,
                'type' => WalletTransactionType::DonationReceived,
                'currency' => 'pink',
                'amount' => $netPayout,
                'balance_before' => $idolPinkBalBefore,
                'balance_after' => $idolPinkBalAfter,
                'held_balance_before' => (float) $lockedIdolWallet->pink_held_balance,
                'held_balance_after' => (float) $lockedIdolWallet->pink_held_balance,
                'status' => WalletTransactionStatus::Completed,
                'reference_type' => Donation::class,
                'reference_id' => $donation->id,
                'idempotency_key' => "donation_received_{$donation->id}",
                'description' => "Донат от «{$donor->name}»",
                'metadata' => [
                    'donation_id' => $donation->id,
                    'donor_id' => $donor->id,
                    'amount_charged' => $amountCharged,
                    'platform_fee' => $feeAmount,
                    'withdrawal_fee' => $withdrawalFee,
                    'fee_covered_by_donor' => $feeCoveredByDonor,
                    'fee_covered_withdrawal' => $feeCoveredWithdrawal,
                    'message' => $message,
                ],
            ]);

            try {
                $idol->notify(new DonationReceivedNotification($donation));
                $this->safeBroadcast(new NewNotification('private', $idol->id));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to dispatch donation notification: '.$e->getMessage());
            }

            return $donation;
        });
    }
}
