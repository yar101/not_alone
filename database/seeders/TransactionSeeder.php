<?php

namespace Database\Seeders;

use App\Models\ContentPack;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ── 1. Safety Guard for Production ──────────────────────────────────
        if (app()->isProduction() && ! ($this->command?->option('force') ?? false)) {
            $this->command?->warn('TransactionSeeder отключен в продакшене для безопасности.');

            return;
        }

        // ── 2. Enforce Mock / Isolated Payment Mode ─────────────────────────
        Config::set('services.payments.mock_purchases', true);
        Config::set('services.payments.driver', 'mock');

        // ── 3. Idempotency Check ────────────────────────────────────────────
        $seededCount = WalletTransaction::whereJsonContains('metadata->seeded', true)->count();
        if ($seededCount >= 25) {
            $this->command?->info("TransactionSeeder: уже создано {$seededCount} тестовых транзакций. Пропуск.");

            return;
        }

        $walletService = app(WalletService::class);
        $orderService = app(OrderService::class);

        // ── 4. Retrieve Test Users ──────────────────────────────────────────
        $customer1 = User::where('email', 'u1@test.com')->first() ?? User::where('is_idol', false)->first();
        $customer2 = User::where('email', 'u3@test.com')->first() ?? User::where('is_idol', false)->skip(1)->first();
        $idol1 = User::where('email', 'u2@test.com')->first() ?? User::where('is_idol', true)->first();
        $idol2 = User::where('email', 'u4@test.com')->first() ?? User::where('is_idol', true)->skip(1)->first();

        if (! $customer1 || ! $idol1) {
            $this->command?->warn('TransactionSeeder: не найдены тестовые пользователи. Убедитесь, что запущен TestUsersSeeder.');

            return;
        }

        // Ensure idols have services
        $idol1Services = Service::where('user_id', $idol1->id)->where('is_active', true)->get();
        if ($idol1Services->isEmpty()) {
            Artisan::call('services:seed', ['--all' => true, '--force' => true]);
            $idol1Services = Service::where('user_id', $idol1->id)->where('is_active', true)->get();
        }
        $idol2Services = $idol2 ? Service::where('user_id', $idol2->id)->where('is_active', true)->get() : collect();

        if ($idol1Services->isEmpty()) {
            $this->command?->warn('TransactionSeeder: у айдолов нет активных услуг для генерации заказов.');

            return;
        }

        $this->command?->info('TransactionSeeder: генерация доменного цикла транзакций и заказов...');

        // Tracking transactions to adjust dates chronologically
        $createdTxIds = [];
        $createdOrders = [];

        $recordTx = function (WalletTransaction $tx) use (&$createdTxIds) {
            $tx->update([
                'metadata' => array_merge($tx->metadata ?? [], [
                    'seeded' => true,
                    'mock' => true,
                    'provider' => 'seed_generator',
                ]),
            ]);
            $createdTxIds[] = $tx->id;
        };

        // ── 5. Generate Initial Deposits ────────────────────────────────────
        // Customer 1 deposits
        $c1Deposits = [
            [2500.00, 'Пополнение баланса через СБП'],
            [5000.00, 'Пополнение с карты *4242'],
            [1500.00, 'Пополнение через Mir Pay'],
            [3000.00, 'Пополнение баланса картой *1089'],
            [10000.00, 'Пополнение счёта (Банковский перевод)'],
            [2000.00, 'Пополнение через СБП'],
            [4000.00, 'Пополнение через Mir Pay'],
        ];

        foreach ($c1Deposits as [$amount, $desc]) {
            $tx = $walletService->deposit(
                $customer1,
                $amount,
                $desc,
                metadata: ['seeded' => true, 'mock' => true]
            );
            $recordTx($tx);
        }

        // Customer 2 deposits (if available)
        if ($customer2) {
            $c2Deposits = [
                [3000.00, 'Пополнение через СБП'],
                [6000.00, 'Пополнение с карты *9012'],
                [2500.00, 'Пополнение баланса'],
            ];
            foreach ($c2Deposits as [$amount, $desc]) {
                $tx = $walletService->deposit(
                    $customer2,
                    $amount,
                    $desc,
                    metadata: ['seeded' => true, 'mock' => true]
                );
                $recordTx($tx);
            }
        }

        // ── 6. Completed Orders Cycle (Hold -> Payout + PlatformFee) ─────────
        // Customer 1 with Idol 1
        $servicesToOrder = $idol1Services->take(3);
        foreach ($servicesToOrder as $srv) {
            for ($rep = 0; $rep < 3; $rep++) {
                [$order] = $orderService->createOrder($customer1, $idol1, [
                    ['id' => $srv->id, 'quantity' => 1],
                ]);
                $orderService->accept($order, $idol1);
                $orderService->pay($order, $customer1);

                // Find hold transaction
                $holdTx = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->where('type', \App\Enums\WalletTransactionType::OrderHold)
                    ->latest('id')
                    ->first();
                if ($holdTx) {
                    $recordTx($holdTx);
                }

                $orderService->confirmCompletion($order, $customer1);

                // Find payout and fee
                $payoutTx = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->where('type', \App\Enums\WalletTransactionType::OrderPayout)
                    ->latest('id')
                    ->first();
                if ($payoutTx) {
                    $recordTx($payoutTx);
                }

                $feeTx = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->where('type', \App\Enums\WalletTransactionType::PlatformFee)
                    ->latest('id')
                    ->first();
                if ($feeTx) {
                    $recordTx($feeTx);
                }

                $createdOrders[] = $order;
            }
        }

        // Customer 2 with Idol 1 / Idol 2
        if ($customer2 && $idol2 && $idol2Services->isNotEmpty()) {
            foreach ($idol2Services->take(2) as $srv) {
                [$order] = $orderService->createOrder($customer2, $idol2, [
                    ['id' => $srv->id, 'quantity' => 1],
                ]);
                $orderService->accept($order, $idol2);
                $orderService->pay($order, $customer2);

                $holdTx = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->where('type', \App\Enums\WalletTransactionType::OrderHold)
                    ->latest('id')
                    ->first();
                if ($holdTx) {
                    $recordTx($holdTx);
                }

                $orderService->confirmCompletion($order, $customer2);

                $payoutTx = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->where('type', \App\Enums\WalletTransactionType::OrderPayout)
                    ->latest('id')
                    ->first();
                if ($payoutTx) {
                    $recordTx($payoutTx);
                }

                $feeTx = WalletTransaction::where('reference_type', Order::class)
                    ->where('reference_id', $order->id)
                    ->where('type', \App\Enums\WalletTransactionType::PlatformFee)
                    ->latest('id')
                    ->first();
                if ($feeTx) {
                    $recordTx($feeTx);
                }

                $createdOrders[] = $order;
            }
        }

        // ── 7. Cancelled Orders Cycle (Hold -> Refund) ──────────────────────
        $cancelReasons = [
            'Не удалось согласовать время проведения встречи',
            'Изменились планы на вечер',
            'Заказ оформлен по ошибке',
        ];

        foreach ($cancelReasons as $reason) {
            $srv = $idol1Services->first();
            [$order] = $orderService->createOrder($customer1, $idol1, [
                ['id' => $srv->id, 'quantity' => 1],
            ]);
            $orderService->accept($order, $idol1);
            $orderService->pay($order, $customer1);

            $holdTx = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', \App\Enums\WalletTransactionType::OrderHold)
                ->latest('id')
                ->first();
            if ($holdTx) {
                $recordTx($holdTx);
            }

            $orderService->cancel($order, $customer1, $reason);

            $refundTx = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', \App\Enums\WalletTransactionType::OrderRefund)
                ->latest('id')
                ->first();
            if ($refundTx) {
                $recordTx($refundTx);
            }

            $createdOrders[] = $order;
        }

        // ── 8. Active Orders in Hold (Paid status) ───────────────────────────
        $srv = $idol1Services->last();
        [$activeOrder] = $orderService->createOrder($customer1, $idol1, [
            ['id' => $srv->id, 'quantity' => 1],
        ]);
        $orderService->accept($activeOrder, $idol1);
        $orderService->pay($activeOrder, $customer1);

        $holdTx = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $activeOrder->id)
            ->where('type', \App\Enums\WalletTransactionType::OrderHold)
            ->latest('id')
            ->first();
        if ($holdTx) {
            $recordTx($holdTx);
        }
        $createdOrders[] = $activeOrder;

        // ── 9. Content Pack Purchases (PackPurchase + PackSale + Fee) ───────
        $packs = ContentPack::where('user_id', $idol1->id)->where('status', 'published')->get();
        if ($packs->isEmpty() && $idol2) {
            $packs = ContentPack::where('user_id', $idol2->id)->where('status', 'published')->get();
        }

        foreach ($packs->take(2) as $pack) {
            // Check buyer has balance
            $buyerWallet = $walletService->getOrCreateWallet($customer1);
            if ($buyerWallet->balance < ($pack->price ?? 500)) {
                $dep = $walletService->deposit($customer1, 2000.00, 'Пополнение для контента');
                $recordTx($dep);
            }

            try {
                $walletService->purchaseContentPack($customer1, $pack, 10.0);

                // Find buyer tx
                $packTx = WalletTransaction::where('reference_type', ContentPack::class)
                    ->where('reference_id', $pack->id)
                    ->where('user_id', $customer1->id)
                    ->latest('id')
                    ->first();
                if ($packTx) {
                    $recordTx($packTx);
                }

                // Find seller tx
                $saleTx = WalletTransaction::where('reference_type', ContentPack::class)
                    ->where('reference_id', $pack->id)
                    ->where('type', \App\Enums\WalletTransactionType::PackSale)
                    ->latest('id')
                    ->first();
                if ($saleTx) {
                    $recordTx($saleTx);
                }

                $packFeeTx = WalletTransaction::where('reference_type', ContentPack::class)
                    ->where('reference_id', $pack->id)
                    ->where('type', \App\Enums\WalletTransactionType::PlatformFee)
                    ->latest('id')
                    ->first();
                if ($packFeeTx) {
                    $recordTx($packFeeTx);
                }
            } catch (\Throwable $e) {
                // Ignore if already purchased
            }
        }

        // ── 10. Idol Withdrawals ────────────────────────────────────────────
        $idolWallet = $walletService->getOrCreateWallet($idol1);
        if ($idolWallet->balance >= 1000) {
            $withdrawAmounts = [
                min(2000.00, (float) $idolWallet->balance * 0.4),
                min(1500.00, (float) $idolWallet->balance * 0.3),
            ];

            foreach ($withdrawAmounts as $wAmount) {
                $wAmount = round($wAmount, 2);
                if ($wAmount >= 100 && $idolWallet->fresh()->balance >= $wAmount) {
                    $wTx = $walletService->withdraw(
                        $idol1,
                        $wAmount,
                        description: 'Вывод средств на карту *'.rand(1000, 9999),
                        metadata: ['seeded' => true, 'mock' => true]
                    );
                    $recordTx($wTx);
                }
            }
        }

        // ── 11. Additional Deposits for Customer 1 to reach >25 transactions ─
        $c1TxCount = WalletTransaction::where('user_id', $customer1->id)->count();
        $needed = max(0, 26 - $c1TxCount);
        for ($k = 1; $k <= $needed; $k++) {
            $dep = $walletService->deposit(
                $customer1,
                (float) (300 + ($k * 100)),
                "Пополнение счёта №{$k}",
                metadata: ['seeded' => true, 'mock' => true]
            );
            $recordTx($dep);
        }

        // ── 12. Chronological Timestamp Adjustment (Last 35 days) ───────────
        $createdTxIds = array_values(array_unique($createdTxIds));
        sort($createdTxIds);
        $totalTx = count($createdTxIds);

        if ($totalTx > 0) {
            $start = Carbon::now()->subDays(35);
            $end = Carbon::now()->subMinutes(15);
            $stepSeconds = max(60, (int) ($start->diffInSeconds($end) / $totalTx));

            $currentTime = $start->copy();
            foreach ($createdTxIds as $index => $txId) {
                $currentTime->addSeconds($stepSeconds + rand(10, 120));
                if ($currentTime->gt($end)) {
                    $currentTime = $end->copy()->subMinutes(rand(1, 10));
                }

                DB::table('wallet_transactions')
                    ->where('id', $txId)
                    ->update([
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime,
                    ]);
            }
        }

        // Adjust associated orders timestamps
        foreach ($createdOrders as $order) {
            $orderTx = WalletTransaction::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->oldest('id')
                ->first();

            if ($orderTx && $orderTx->created_at) {
                $orderDate = Carbon::parse($orderTx->created_at);
                $paidDate = $order->paid_at ? $orderDate->copy()->addMinutes(rand(5, 30)) : null;
                $completedDate = $order->completed_at ? ($paidDate ?? $orderDate)->copy()->addHours(rand(1, 12)) : null;

                DB::table('orders')->where('id', $order->id)->update([
                    'created_at' => $orderDate,
                    'updated_at' => $completedDate ?? $paidDate ?? $orderDate,
                    'paid_at' => $paidDate,
                    'completed_at' => $completedDate,
                ]);
            }
        }

        $finalC1Tx = WalletTransaction::where('user_id', $customer1->id)->count();
        $finalTotalTx = WalletTransaction::count();

        $this->command?->info("TransactionSeeder завершён! Всего транзакций: {$finalTotalTx} (у пользователя {$customer1->name}: {$finalC1Tx}).");
    }
}
