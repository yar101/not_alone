<?php

namespace App\Services\Mandarin;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\MandarinGatewayException;
use App\Models\ProcessedWebhookEvent;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MandarinWebhookService
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Verify webhook signature and replay protection window.
     */
    public function verifySignature(string $rawPayload, ?string $signature, ?string $timestamp): bool
    {
        $isSandbox = (bool) config('services.mandarin.is_sandbox', true);
        $secret = config('services.mandarin.webhook_secret', '');

        // Replay protection: 300 seconds window
        if ($timestamp !== null) {
            $ts = is_numeric($timestamp) ? (int) $timestamp : strtotime($timestamp);
            if ($ts && abs(now()->timestamp - $ts) > 300) {
                Log::warning('Mandarin webhook timestamp rejected (replay protection)', [
                    'timestamp' => $timestamp,
                    'now' => now()->timestamp,
                ]);

                return false;
            }
        }

        // Test signature shortcut for testing/local
        if (($isSandbox || app()->environment(['local', 'testing'])) && $signature === 'test-signature') {
            return true;
        }

        if (empty($secret)) {
            return $isSandbox || app()->environment(['local', 'testing']);
        }

        if (empty($signature)) {
            return false;
        }

        // Verify either payload-only or timestamp.payload
        $expected1 = hash_hmac('sha256', ($timestamp ? $timestamp.'.' : '').$rawPayload, $secret);
        $expected2 = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected1, $signature) || hash_equals($expected2, $signature);
    }

    /**
     * Process an incoming Mandarin webhook.
     */
    public function handleWebhook(
        array $payload,
        string $rawPayload,
        ?string $signature = null,
        ?string $timestamp = null
    ): array {
        if (! $this->verifySignature($rawPayload, $signature, $timestamp)) {
            Log::error('Mandarin webhook signature verification failed', [
                'signature' => $signature,
                'timestamp' => $timestamp,
            ]);
            throw new MandarinGatewayException('Invalid webhook signature or expired timestamp');
        }

        $eventId = (string) ($payload['event_id'] ?? $payload['id'] ?? md5($rawPayload));
        $eventType = (string) ($payload['event'] ?? $payload['action'] ?? $payload['type'] ?? 'unknown');
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? $payload['data']['idempotency_key'] ?? $eventId);

        // 1. Idempotency check: prevent duplicate event processing
        $existing = ProcessedWebhookEvent::where('event_id', $eventId)->first();
        if ($existing) {
            Log::info("Mandarin webhook event {$eventId} already processed; skipping");

            return [
                'status' => 'already_processed',
                'event_id' => $eventId,
                'event_type' => $eventType,
            ];
        }

        Log::info("Processing Mandarin webhook {$eventId}: {$eventType}");

        // 2. Event Dispatching
        $data = $payload['data'] ?? $payload;

        DB::transaction(function () use ($eventType, $data, $eventId, $idempotencyKey, $payload) {
            match ($eventType) {
                'invoice.paid', 'payment.succeeded' => $this->handlePaymentSucceeded($data, $idempotencyKey),
                'self_employed.bound' => $this->handleSelfEmployedBound($data),
                'payout.succeeded' => $this->handlePayoutSucceeded($data, $idempotencyKey),
                'payout.failed' => $this->handlePayoutFailed($data, $idempotencyKey),
                'payout.pending_bank' => $this->handlePayoutPendingBank($data, $idempotencyKey),
                default => Log::info("Unhandled Mandarin webhook event type: {$eventType}"),
            };

            ProcessedWebhookEvent::create([
                'event_id' => $eventId,
                'idempotency_key' => $idempotencyKey,
                'event_type' => $eventType,
                'payload' => $payload,
                'processed_at' => now(),
            ]);
        });

        return [
            'status' => 'success',
            'event_id' => $eventId,
            'event_type' => $eventType,
        ];
    }

    /**
     * Handle payment/invoice succeeded (credit customer blue coins).
     */
    protected function handlePaymentSucceeded(array $data, string $idempotencyKey): void
    {
        $userId = (int) ($data['customer_id'] ?? $data['user_id'] ?? $data['metadata']['user_id'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0.0);

        if ($userId <= 0 || $amount <= 0.0) {
            Log::warning('Payment succeeded webhook with missing user_id or non-positive amount', $data);

            return;
        }

        $user = User::find($userId);
        if (! $user) {
            Log::warning("Customer #{$userId} not found for payment webhook");

            return;
        }

        $desc = 'Пополнение баланса через Mandarin (100% зачисление, эквайринг 0%)';
        $meta = [
            'gateway' => 'mandarin',
            'gateway_operation_id' => $data['operation_id'] ?? $data['id'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'sbp',
            'fiscal_receipt_type' => '54_fz_advance',
            'fiscal_status' => 'registered',
        ];

        $this->walletService->deposit(
            user: $user,
            amount: $amount,
            description: $desc,
            idempotencyKey: $idempotencyKey,
            metadata: $meta,
            applyFee: false // 0% acquiring fee for customers
        );

        Log::info("Credited {$amount} blue coins to user #{$userId} via Mandarin invoice");
    }

    /**
     * Handle self_employed.bound: idol verified in FNS "Мой налог".
     */
    protected function handleSelfEmployedBound(array $data): void
    {
        $inn = trim((string) ($data['inn'] ?? ''));
        if (empty($inn)) {
            Log::warning('Self employed bound webhook missing INN', $data);

            return;
        }

        $idol = User::where('inn', $inn)->first();
        if (! $idol) {
            Log::warning("Idol with INN {$inn} not found for self_employed.bound");

            return;
        }

        $idol->tax_status = 'self_employed';
        $idol->tax_verified_at = now();

        if (! empty($idol->sbp_bank_id) && ! empty($idol->payout_phone)) {
            $idol->is_payout_ready = true;
        }

        $idol->save();

        Log::info("Idol #{$idol->id} (INN: {$inn}) tax status verified as self_employed via webhook");
    }

    /**
     * Handle payout.succeeded: SBP payout settled to idol.
     */
    protected function handlePayoutSucceeded(array $data, string $idempotencyKey): void
    {
        $tx = $this->findWithdrawalTransaction($data, $idempotencyKey);
        if (! $tx) {
            Log::warning('Withdrawal transaction not found for payout.succeeded', $data);

            return;
        }

        $meta = $tx->metadata ?? [];
        $meta['fns_receipt_url'] = $data['fns_receipt_url'] ?? $meta['fns_receipt_url'] ?? null;
        $meta['settled_at'] = now()->toISOString();
        $meta['payout_status'] = 'settled';

        $tx->update([
            'status' => WalletTransactionStatus::Completed,
            'metadata' => $meta,
        ]);

        Log::info("Payout transaction #{$tx->id} marked as Completed (Settled)");
    }

    /**
     * Handle payout.failed: SBP payout failed, unfreeze held pink balance.
     */
    protected function handlePayoutFailed(array $data, string $idempotencyKey): void
    {
        $tx = $this->findWithdrawalTransaction($data, $idempotencyKey);
        if (! $tx) {
            Log::warning('Withdrawal transaction not found for payout.failed', $data);

            return;
        }

        if ($tx->status === WalletTransactionStatus::Failed) {
            return;
        }

        $wallet = Wallet::where('id', $tx->wallet_id)->lockForUpdate()->first();
        if ($wallet) {
            $payoutAmount = abs((float) $tx->amount);
            // Return funds from pink_held_balance to pink_balance
            $heldBefore = (float) $wallet->pink_held_balance;
            $balBefore = (float) $wallet->pink_balance;

            $heldAfter = max(0.00, (float) bcsub((string) $heldBefore, (string) $payoutAmount, 2));
            $balAfter = (float) bcadd((string) $balBefore, (string) $payoutAmount, 2);

            $wallet->update([
                'pink_balance' => $balAfter,
                'pink_held_balance' => $heldAfter,
            ]);
        }

        $meta = $tx->metadata ?? [];
        $meta['failure_reason'] = $data['failure_reason'] ?? $data['error'] ?? 'Bank rejection';
        $meta['payout_status'] = 'failed';

        $tx->update([
            'status' => WalletTransactionStatus::Failed,
            'metadata' => $meta,
        ]);

        Log::warning("Payout transaction #{$tx->id} failed; {$tx->amount} returned to idol pink balance");
    }

    /**
     * Handle payout.pending_bank: bank clearing in progress.
     */
    protected function handlePayoutPendingBank(array $data, string $idempotencyKey): void
    {
        $tx = $this->findWithdrawalTransaction($data, $idempotencyKey);
        if (! $tx) {
            return;
        }

        $meta = $tx->metadata ?? [];
        $meta['payout_status'] = 'pending_bank';

        $tx->update([
            'status' => WalletTransactionStatus::PendingBank,
            'metadata' => $meta,
        ]);

        Log::info("Payout transaction #{$tx->id} transitioned to PendingBank");
    }

    /**
     * Locate the withdrawal transaction by idempotency key or operation id.
     */
    protected function findWithdrawalTransaction(array $data, string $idempotencyKey): ?WalletTransaction
    {
        $query = WalletTransaction::where('type', WalletTransactionType::Withdrawal);

        if (! empty($data['operation_id'])) {
            $tx = (clone $query)->where('metadata->gateway_operation_id', $data['operation_id'])->first();
            if ($tx) {
                return $tx;
            }
        }

        return $query->where('idempotency_key', $idempotencyKey)->first();
    }
}
