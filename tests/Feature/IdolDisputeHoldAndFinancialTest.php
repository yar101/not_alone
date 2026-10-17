<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Jobs\ReleaseIdolOrderHoldJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\IdolPayoutReleasedNotification;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Cache::flush();
    Config::set('services.payments.mock_purchases', false);
    PlatformSetting::set('platform_fee_percent', 20.0);
    PlatformSetting::set('order_dispute_window_minutes', 60);
});

function createTestOrderWithWallets(float $orderAmount = 1000.00, float $customerBalance = 2000.00): array
{
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $customerWallet = $walletService->getOrCreateWallet($customer);
    $idolWallet = $walletService->getOrCreateWallet($idol);

    // Give customer initial balance without fees for clean testing
    $customerWallet->update(['balance' => $customerBalance]);

    $category = ServiceCategory::create(['name' => 'Тест']);
    $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Тестовая услуга',
        'price' => $orderAmount,
        'is_active' => true,
        'status' => 'approved',
    ]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'service_id' => $service->id,
        'price' => $orderAmount,
        'quantity' => 1,
    ]);

    return compact('customer', 'idol', 'customerWallet', 'idolWallet', 'service', 'order');
}

it('holds net earnings in idol held_balance upon order completion and schedules release job', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $idol = $data['idol'];

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    // 1. Pay order
    $orderService->pay($order, $customer);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid);

    $customerWallet = $walletService->getOrCreateWallet($customer);
    expect((float) $customerWallet->balance)->toBe(1000.00);
    expect((float) $customerWallet->held_balance)->toBe(1000.00);

    // 2. Customer confirms completion -> order Completed
    $orderService->confirmCompletion($order, $customer);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed);
    expect($order->payout_released_at)->toBeNull();

    // Customer held balance released
    $customerWallet->refresh();
    expect((float) $customerWallet->held_balance)->toBe(0.00);

    // Idol: net earnings (1000 - 20% = 800) in held_balance, NOT available balance
    $idolWallet = $walletService->getOrCreateWallet($idol);
    expect((float) $idolWallet->balance)->toBe(0.00);
    expect((float) $idolWallet->held_balance)->toBe(800.00);

    // Pending payout transaction exists with held metadata
    $payoutTx = WalletTransaction::where('reference_type', Order::class)
        ->where('reference_id', $order->id)
        ->where('type', WalletTransactionType::OrderPayout)
        ->first();

    expect($payoutTx)->not->toBeNull();
    expect($payoutTx->status)->toBe(WalletTransactionStatus::Pending);
    expect((float) $payoutTx->amount)->toBe(1000.00);
    expect($payoutTx->metadata['held'])->toBeTrue();

    $feeTx = WalletTransaction::where('reference_type', Order::class)
        ->where('reference_id', $order->id)
        ->where('type', WalletTransactionType::PlatformFee)
        ->first();
    expect($feeTx)->not->toBeNull();
    expect($feeTx->status)->toBe(WalletTransactionStatus::Pending);
    expect((float) $feeTx->amount)->toBe(-200.00);

    // Delayed job pushed to queue
    Queue::assertPushed(ReleaseIdolOrderHoldJob::class, function ($job) use ($order) {
        return $job->order->id === $order->id;
    });
});

it('blocks idol from withdrawing held funds before dispute window expires', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $idol = $data['idol'];

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    $orderService->pay($order, $customer);
    $orderService->confirmCompletion($order, $customer);

    $idolWallet = $walletService->getOrCreateWallet($idol);
    expect((float) $idolWallet->held_balance)->toBe(800.00);
    expect((float) $idolWallet->balance)->toBe(0.00);

    // Attempting to withdraw held funds must throw InsufficientFundsException
    expect(fn () => $walletService->withdraw($idol, 500.00))
        ->toThrow(InsufficientFundsException::class);
});

it('releases idol held payout to available balance and notifies idol', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $idol = $data['idol'];

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    $orderService->pay($order, $customer);
    $orderService->confirmCompletion($order, $customer);

    $order->refresh();

    // Release payout
    $releasedTx = $walletService->releaseIdolPayout($order);
    expect($releasedTx)->not->toBeNull();
    expect($releasedTx->status)->toBe(WalletTransactionStatus::Completed);

    $idolWallet = $walletService->getOrCreateWallet($idol);
    expect((float) $idolWallet->held_balance)->toBe(0.00);
    expect((float) $idolWallet->balance)->toBe(800.00);

    $order->refresh();
    expect($order->payout_released_at)->not->toBeNull();

    Notification::assertSentTo($idol, IdolPayoutReleasedNotification::class);
});

it('guarantees idempotency: releasing hold multiple times does not duplicate payout', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $idol = $data['idol'];

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    $orderService->pay($order, $customer);
    $orderService->confirmCompletion($order, $customer);

    $order->refresh();

    // 1st release
    $tx1 = $walletService->releaseIdolPayout($order);
    expect($tx1)->not->toBeNull();

    $idolWallet = $walletService->getOrCreateWallet($idol);
    expect((float) $idolWallet->balance)->toBe(800.00);

    // 2nd release attempt directly
    $tx2 = $walletService->releaseIdolPayout($order);
    expect($tx2)->toBeNull();

    // Balance remains exactly 800
    $idolWallet->refresh();
    expect((float) $idolWallet->balance)->toBe(800.00);

    // 3rd release attempt via Artisan command fallback
    $this->artisan('orders:release-idol-holds')->assertExitCode(0);

    $idolWallet->refresh();
    expect((float) $idolWallet->balance)->toBe(800.00);
    expect((float) $idolWallet->held_balance)->toBe(0.00);
});

it('refunds customer from held_balance without touching idol available balance when dispute won during hold', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $idol = $data['idol'];

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    // Give idol some existing balance (e.g. from other orders)
    $idolWallet = $walletService->getOrCreateWallet($idol);
    $idolWallet->update(['balance' => 500.00]);

    $orderService->pay($order, $customer);
    $orderService->confirmCompletion($order, $customer);

    $idolWallet->refresh();
    expect((float) $idolWallet->balance)->toBe(500.00);
    expect((float) $idolWallet->held_balance)->toBe(800.00);

    // Customer files dispute
    $orderService->dispute($order, $customer, 'Качество услуги не соответствует ожиданиям', 'Подробности...');
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Disputed);

    // Admin resolves dispute in customer favor -> refunds order
    $refundTx = $walletService->refundHold($order, 'Решение в пользу клиента');
    expect($refundTx)->not->toBeNull();

    // Idol's held_balance cleared, but available balance (500) remains untouched!
    $idolWallet->refresh();
    expect((float) $idolWallet->balance)->toBe(500.00);
    expect((float) $idolWallet->held_balance)->toBe(0.00);

    // Customer gets full 1000 refund back to balance
    $customerWallet = $walletService->getOrCreateWallet($customer);
    expect((float) $customerWallet->balance)->toBe(2000.00);

    // No phantom clawback transaction recorded because payout was never released to available balance
    $clawbackTx = WalletTransaction::where('type', WalletTransactionType::OrderClawback)
        ->where('reference_id', $order->id)
        ->first();
    expect($clawbackTx)->toBeNull();

    // Pending payout and fee cancelled
    $payoutTx = WalletTransaction::where('type', WalletTransactionType::OrderPayout)
        ->where('reference_id', $order->id)
        ->first();
    expect($payoutTx->status)->toBe(WalletTransactionStatus::Cancelled);

    $feeTx = WalletTransaction::where('type', WalletTransactionType::PlatformFee)
        ->where('reference_id', $order->id)
        ->first();
    expect($feeTx->status)->toBe(WalletTransactionStatus::Cancelled);
});

it('releases payout immediately when admin resolves dispute in idol favor', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $idol = $data['idol'];

    $admin = \App\Models\Admin::create(['name' => 'Admin', 'email' => 'admin_test1@test.com', 'password' => 'secret']);

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    $orderService->pay($order, $customer);
    $orderService->confirmCompletion($order, $customer);

    // Customer disputes
    $orderService->dispute($order, $customer, 'Претензия', 'Подробности');
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Disputed);

    // Admin rejects dispute -> transitions to Completed
    $orderService->adminTransition($order, OrderStatus::Completed, $admin->id, 'Претензия необоснованна');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed);
    expect($order->payout_released_at)->not->toBeNull();

    $idolWallet = $walletService->getOrCreateWallet($idol);
    expect((float) $idolWallet->held_balance)->toBe(0.00);
    expect((float) $idolWallet->balance)->toBe(800.00);
});

it('allows negative balance on clawback if idol already withdrew released payout', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $idol = $data['idol'];

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    $orderService->pay($order, $customer);
    $orderService->confirmCompletion($order, $customer);

    // Payout released after dispute window
    $walletService->releaseIdolPayout($order);

    $idolWallet = $walletService->getOrCreateWallet($idol);
    expect((float) $idolWallet->balance)->toBe(800.00);

    // Idol withdraws all money (balance becomes 0)
    $idolWallet->update(['balance' => 0.00]);

    // Dispute won by customer after payout release -> clawback triggers
    $refundTx = $walletService->refundHold($order, 'Возврат после разблокировки');
    expect($refundTx)->not->toBeNull();

    // Idol balance goes negative without DB constraint failure!
    $idolWallet->refresh();
    expect((float) $idolWallet->balance)->toBe(-800.00);
    expect((float) $idolWallet->held_balance)->toBe(0.00);
});

it('prevents double-hold if order is transitioned Paid -> Disputed -> Paid', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $admin = \App\Models\Admin::create(['name' => 'Admin', 'email' => 'admin_test2@test.com', 'password' => 'secret']);

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    // 1st pay
    $orderService->pay($order, $customer);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid);

    $customerWallet = $walletService->getOrCreateWallet($customer);
    expect((float) $customerWallet->held_balance)->toBe(1000.00);

    // Transition to Disputed
    $orderService->dispute($order, $customer, 'Спор', 'Детали');

    // Admin transitions back to Paid
    $orderService->adminTransition($order, OrderStatus::Paid, $admin->id, 'Возврат в работу');

    $customerWallet->refresh();
    // Held balance should still be 1000, NOT 2000
    expect((float) $customerWallet->held_balance)->toBe(1000.00);

    $holdCount = WalletTransaction::where('reference_type', Order::class)
        ->where('reference_id', $order->id)
        ->where('type', WalletTransactionType::OrderHold)
        ->where('status', WalletTransactionStatus::Completed)
        ->count();

    expect($holdCount)->toBe(1);
});

it('supports filtering holds in wallet transactions API', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $idol = $data['idol'];

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    $orderService->pay($order, $customer);
    $orderService->confirmCompletion($order, $customer);

    // Act as customer - filter holds
    $response = $this->actingAs($customer)->getJson('/api/wallet?filter=holds');
    $response->assertOk();
    $txs = $response->json('transactions');
    expect(count($txs))->toBeGreaterThanOrEqual(2);
    expect($txs[0]['type'])->toBe('order_hold_release');
    expect($txs[1]['type'])->toBe('order_hold');

    // Act as idol - filter holds
    $responseIdol = $this->actingAs($idol)->getJson('/api/wallet?filter=holds');
    $responseIdol->assertOk();
    $txsIdol = $responseIdol->json('transactions');
    expect(count($txsIdol))->toBeGreaterThanOrEqual(1);
    $payoutItem = collect($txsIdol)->firstWhere('type', 'order_payout');
    expect($payoutItem)->not->toBeNull();
    expect($payoutItem['status'])->toBe('pending');
});

it('handles re-opened order refunding correctly without trapping funds (Paid -> Cancelled -> Paid -> Cancelled)', function () {
    $data = createTestOrderWithWallets(1000.00, 2000.00);
    $order = $data['order'];
    $customer = $data['customer'];
    $admin = \App\Models\Admin::create(['name' => 'Admin2', 'email' => 'admin_test3@test.com', 'password' => 'secret']);

    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    // 1. First payment -> customer held = 1000, balance = 1000
    $orderService->pay($order, $customer);
    $customerWallet = $walletService->getOrCreateWallet($customer);
    expect((float) $customerWallet->balance)->toBe(1000.00);
    expect((float) $customerWallet->held_balance)->toBe(1000.00);

    // 2. Admin cancels order -> refundHold releases the hold
    $orderService->adminTransition($order, OrderStatus::Cancelled, $admin->id, 'Первая отмена');
    $customerWallet->refresh();
    expect((float) $customerWallet->balance)->toBe(2000.00);
    expect((float) $customerWallet->held_balance)->toBe(0.00);

    // 3. Admin re-opens to Paid -> creates a second hold
    $orderService->adminTransition($order, OrderStatus::Paid, $admin->id, 'Переоткрытие заказа');
    $customerWallet->refresh();
    expect((float) $customerWallet->balance)->toBe(1000.00);
    expect((float) $customerWallet->held_balance)->toBe(1000.00);

    // 4. Admin cancels again -> second refundHold must refund the second hold, NOT no-op!
    $orderService->adminTransition($order, OrderStatus::Cancelled, $admin->id, 'Вторая отмена');
    $customerWallet->refresh();
    expect((float) $customerWallet->balance)->toBe(2000.00);
    expect((float) $customerWallet->held_balance)->toBe(0.00);
});

it('strictly maintains ledger invariant sum(completed transactions) == balance for idol and customer', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $orderService = app(OrderService::class);

    // Deposit with fee
    $walletService->deposit($customer, 5000.00, true);

    $category = ServiceCategory::create(['name' => 'Услуга']);
    $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);
    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Сервис',
        'price' => 2000.00,
        'is_active' => true,
    ]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'service_id' => $service->id,
        'price' => 2000.00,
        'quantity' => 1,
    ]);

    // Pay
    $orderService->pay($order, $customer);
    // Complete
    $orderService->confirmCompletion($order, $customer);
    // Release idol payout
    $walletService->releaseIdolPayout($order);

    $customerWallet = $customer->wallet->fresh();
    $idolWallet = $idol->wallet->fresh();

    // Verify ledger invariants: sum of completed transaction amounts must equal wallet balance exactly
    $customerTxSum = (float) WalletTransaction::where('wallet_id', $customerWallet->id)
        ->where('status', WalletTransactionStatus::Completed)
        ->sum('amount');
    $idolTxSum = (float) WalletTransaction::where('wallet_id', $idolWallet->id)
        ->where('status', WalletTransactionStatus::Completed)
        ->sum('amount');

    expect(abs($customerTxSum - (float) $customerWallet->balance))->toBeLessThan(0.001);
    expect(abs($idolTxSum - (float) $idolWallet->balance))->toBeLessThan(0.001);
});

