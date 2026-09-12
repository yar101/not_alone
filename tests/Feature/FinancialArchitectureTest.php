<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\ContentPack;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Event::fake();
});

it('creates a wallet for a user on demand with initial zero balance', function () {
    $user = User::factory()->create();
    $service = app(WalletService::class);

    $wallet = $service->getOrCreateWallet($user);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->user_id)->toBe($user->id);
    expect((float) $wallet->balance)->toBe(0.00);
    expect((float) $wallet->held_balance)->toBe(0.00);
    expect($wallet->currency)->toBe('RUB');
    expect($wallet->total_balance)->toBe(0.00);
});

it('deposits funds and records a completed audit transaction', function () {
    $user = User::factory()->create();
    $service = app(WalletService::class);

    $tx = $service->deposit($user, 1500.50, 'Тестовый депозит', 'dep_123');

    expect($tx)->toBeInstanceOf(WalletTransaction::class);
    expect($tx->type)->toBe(WalletTransactionType::Deposit);
    expect($tx->status)->toBe(WalletTransactionStatus::Completed);
    expect((float) $tx->amount)->toBe(1500.50);
    expect((float) $tx->balance_before)->toBe(0.00);
    expect((float) $tx->balance_after)->toBe(1500.50);

    $wallet = $user->wallet->fresh();
    expect((float) $wallet->balance)->toBe(1500.50);
    expect($wallet->total_balance)->toBe(1500.50);

    // Test idempotency with same idempotency_key
    $tx2 = $service->deposit($user, 1500.50, 'Повторный депозит', 'dep_123');
    expect($tx2->id)->toBe($tx->id);
    expect((float) $user->wallet->fresh()->balance)->toBe(1500.50);
});

it('holds funds in escrow when balance is sufficient and moves balance to held_balance', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);
    $service = app(WalletService::class);

    $service->deposit($customer, 2000.00);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
    ]);

    $tx = $service->hold($customer, 1200.00, $order, "Холд по заказу #{$order->id}");

    expect($tx->type)->toBe(WalletTransactionType::OrderHold);
    expect((float) $tx->amount)->toBe(-1200.00);
    expect((float) $tx->balance_before)->toBe(2000.00);
    expect((float) $tx->balance_after)->toBe(800.00);
    expect((float) $tx->held_balance_after)->toBe(1200.00);

    $customerWallet = $customer->wallet->fresh();
    expect((float) $customerWallet->balance)->toBe(800.00);
    expect((float) $customerWallet->held_balance)->toBe(1200.00);
    expect($customerWallet->total_balance)->toBe(2000.00);
});

it('throws InsufficientFundsException when holding more than available balance', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);
    $service = app(WalletService::class);

    $service->deposit($customer, 300.00);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
    ]);

    expect(fn () => $service->hold($customer, 500.00, $order))
        ->toThrow(InsufficientFundsException::class);
});

it('releases escrow hold to idol with platform fee deduction on order completion', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);
    $service = app(WalletService::class);

    $service->deposit($customer, 1000.00);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
    ]);

    $service->hold($customer, 1000.00, $order);

    // Platform fee 10%: held 1000 => fee 100, idol gets 900
    $result = $service->releaseHold($order, 10.0);

    expect($result['payout'])->toBeInstanceOf(WalletTransaction::class);
    expect((float) $result['payout']->amount)->toBe(900.00);
    expect((float) $result['fee']->amount)->toBe(-100.00);

    $customerWallet = $customer->wallet->fresh();
    $idolWallet = $idol->wallet->fresh();

    expect((float) $customerWallet->balance)->toBe(0.00);
    expect((float) $customerWallet->held_balance)->toBe(0.00);
    expect((float) $idolWallet->balance)->toBe(900.00);

    // Idempotent check
    $repeat = $service->releaseHold($order, 10.0);
    expect($repeat['payout']->id)->toBe($result['payout']->id);
    expect((float) $idol->wallet->fresh()->balance)->toBe(900.00);
});

it('refunds escrow hold to customer when order is cancelled or refunded', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);
    $service = app(WalletService::class);

    $service->deposit($customer, 1500.00);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
    ]);

    $service->hold($customer, 1500.00, $order);

    expect((float) $customer->wallet->fresh()->balance)->toBe(0.00);
    expect((float) $customer->wallet->fresh()->held_balance)->toBe(1500.00);

    $refundTx = $service->refundHold($order, 'Заказ отменён');

    expect($refundTx->type)->toBe(WalletTransactionType::OrderRefund);
    expect((float) $refundTx->amount)->toBe(1500.00);

    $customerWallet = $customer->wallet->fresh();
    expect((float) $customerWallet->balance)->toBe(1500.00);
    expect((float) $customerWallet->held_balance)->toBe(0.00);
});

it('integrates escrow lifecycle into OrderService pay, cancel, and complete transitions', function () {
    Config::set('services.payments.mock_purchases', false);

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);
    $walletService = app(WalletService::class);
    $orderService = app(OrderService::class);

    $walletService->deposit($customer, 3000.00);

    $category = ServiceCategory::create(['name' => 'Разговоры']);
    $unit = ServiceTimeUnit::create(['name' => '30 мин']);
    $srv = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $unit->id,
        'name' => 'Аудиочат',
        'price' => 1000,
        'is_active' => true,
        'status' => 'approved',
    ]);

    // Create order with items
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
    ]);
    OrderItem::create(['order_id' => $order->id, 'service_id' => $srv->id, 'price' => 1000, 'quantity' => 2]);

    expect($order->fresh()->total_price)->toBe(2000.0);

    // 1. Pay order -> funds held
    $orderService->pay($order, $customer);
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    expect((float) $customer->wallet->fresh()->balance)->toBe(1000.00);
    expect((float) $customer->wallet->fresh()->held_balance)->toBe(2000.00);

    // 2. Complete order -> idol receives payout (10% fee => 2000 - 200 = 1800)
    $orderService->confirmCompletion($order, $customer);
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    expect((float) $customer->wallet->fresh()->held_balance)->toBe(0.00);
    expect((float) $idol->wallet->fresh()->balance)->toBe(1800.00);
});

it('handles content pack purchase with live wallet transactions', function () {
    Config::set('services.payments.mock_purchases', false);

    $buyer = User::factory()->create();
    $seller = User::factory()->create(['is_idol' => true]);
    $walletService = app(WalletService::class);

    $walletService->deposit($buyer, 1500.00);

    $pack = ContentPack::create([
        'user_id' => $seller->id,
        'title' => 'Эксклюзивный сет',
        'price' => 500,
        'status' => 'published',
    ]);

    $purchase = $walletService->purchaseContentPack($buyer, $pack, 10.0);

    expect($purchase)->not->toBeNull();
    expect($purchase->price_paid)->toBe(500);

    // Buyer deducted 500, seller credited 450 (10% fee = 50)
    expect((float) $buyer->wallet->fresh()->balance)->toBe(1000.00);
    expect((float) $seller->wallet->fresh()->balance)->toBe(450.00);
});

it('provides wallet data via JSON', function () {
    $user = User::factory()->create();
    $walletService = app(WalletService::class);
    $walletService->deposit($user, 500.00);

    $response = $this->actingAs($user)->getJson(route('wallet.data'));
    $response->assertOk();
    $response->assertJsonPath('wallet.balance', 500);
    $response->assertJsonPath('wallet.currency', 'RUB');
    expect($response->json('transactions'))->toHaveCount(1);
});

it('renders wallet page with inertia', function () {
    $user = User::factory()->create();
    $walletService = app(WalletService::class);
    $walletService->deposit($user, 300.00);

    $response = $this->actingAs($user)->get(route('wallet.show'));
    $response->assertOk();
});

it('supports test deposits via API', function () {
    $user = User::factory()->create();
    $walletService = app(WalletService::class);
    $walletService->deposit($user, 500.00);

    $depResponse = $this->actingAs($user)->postJson(route('wallet.deposit'), ['amount' => 250]);
    $depResponse->assertOk();
    $depResponse->assertJsonPath('success', true);
    $depResponse->assertJsonPath('balance', 750);
    expect((float) $user->wallet->fresh()->balance)->toBe(750.00);
});
