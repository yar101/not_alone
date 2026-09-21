<?php

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.payments.mock_purchases' => false]);
});

function createSecurityTestOrder(float $orderAmount = 1000.00, float $customerBalance = 5000.00): array
{
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $customerWallet = $walletService->getOrCreateWallet($customer);
    $idolWallet = $walletService->getOrCreateWallet($idol);

    $customerWallet->update(['balance' => $customerBalance]);

    $category = ServiceCategory::create(['name' => 'Тест_' . uniqid()]);
    $timeUnit = ServiceTimeUnit::create(['name' => 'Час_' . uniqid()]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Услуга для теста',
        'price' => $orderAmount,
        'is_active' => true,
        'status' => 'approved',
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
    ]);

    $order->items()->create([
        'service_id' => $service->id,
        'quantity' => 1,
        'price' => $orderAmount,
    ]);

    return compact('customer', 'idol', 'order', 'service');
}

it('prevents account deletion when user has negative balance', function () {
    $user = User::factory()->create();
    $wallet = Wallet::firstOrCreate(
        ['user_id' => $user->id],
        ['balance' => -150.00, 'held_balance' => 0.00, 'currency' => 'RUB', 'is_active' => true]
    );

    $response = $this->actingAs($user)->delete(route('settings.destroy'), [
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('password');
    expect($user->fresh())->not->toBeNull();
});

it('prevents account deletion when user has funds in held_balance', function () {
    $user = User::factory()->create();
    $wallet = Wallet::firstOrCreate(
        ['user_id' => $user->id],
        ['balance' => 500.00, 'held_balance' => 200.00, 'currency' => 'RUB', 'is_active' => true]
    );

    $response = $this->actingAs($user)->delete(route('settings.destroy'), [
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('password');
    expect($user->fresh())->not->toBeNull();
});

it('prevents account deletion when user has active orders', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
    ]);

    $response = $this->actingAs($customer)->delete(route('settings.destroy'), [
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('password');
    expect($customer->fresh())->not->toBeNull();
});

it('correctly charges customer again if an order was refunded and admin transitions back to Paid', function () {
    $data = createSecurityTestOrder(1000.00, 5000.00);
    $customer = $data['customer'];
    $order = $data['order'];

    $walletService = app(WalletService::class);
    $orderService = app(OrderService::class);

    // 1. Pay order -> 1000 held
    $orderService->pay($order, $customer);
    expect((float) $customer->wallet->fresh()->held_balance)->toBe(1000.00);
    expect($walletService->hasActiveHold($order))->toBeTrue();

    // 2. Cancel order -> 1000 refunded
    $orderService->cancel($order, $customer, 'Changed mind');
    expect((float) $customer->wallet->fresh()->held_balance)->toBe(0.00);
    expect($walletService->hasActiveHold($order))->toBeFalse();

    // 3. Admin transitions to Paid again -> must charge customer and hold 1000 again!
    $admin = \App\Models\Admin::create([
        'name' => 'Admin',
        'email' => 'admin_sec@test.com',
        'password' => bcrypt('password'),
    ]);
    $orderService->adminTransition($order, OrderStatus::Paid, $admin->id, 'Reopened');

    expect((float) $customer->wallet->fresh()->held_balance)->toBe(1000.00);
    expect($walletService->hasActiveHold($order))->toBeTrue();

    // Total OrderHold transactions should be 2
    $holdsCount = WalletTransaction::where('reference_type', Order::class)
        ->where('reference_id', $order->id)
        ->where('type', WalletTransactionType::OrderHold)
        ->count();
    expect($holdsCount)->toBe(2);
});

it('releases idol hold payout using forceFill without mass assignment exception', function () {
    $data = createSecurityTestOrder(1000.00, 2000.00);
    $customer = $data['customer'];
    $order = $data['order'];

    $walletService = app(WalletService::class);
    $orderService = app(OrderService::class);

    $orderService->pay($order, $customer);
    $orderService->confirmCompletion($order, $customer);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed);

    // Call releaseIdolPayout
    $payoutTx = $walletService->releaseIdolPayout($order);
    expect($payoutTx)->not->toBeNull();
    expect($payoutTx->status)->toBe(WalletTransactionStatus::Completed);
    expect($order->fresh()->payout_released_at)->not->toBeNull();
});

it('prevents double purchase of content packs and enforces transaction locking', function () {
    $seller = User::factory()->create(['is_idol' => true]);
    $buyer = User::factory()->create();
    $walletService = app(WalletService::class);

    $walletService->deposit($buyer, 1000.00);

    $pack = ContentPack::create([
        'user_id' => $seller->id,
        'title' => 'Тестовый пак',
        'price' => 200.00,
        'status' => 'published',
    ]);

    $purchase = $walletService->purchaseContentPack($buyer, $pack, 10.0);
    expect($purchase)->not->toBeNull();

    // Second purchase attempt must fail with DomainException
    expect(fn () => $walletService->purchaseContentPack($buyer, $pack, 10.0))
        ->toThrow(\DomainException::class, 'Контент-пак уже приобретён.');

    // Buyer balance: 960 (from deposit) - 200 = 760
    expect((float) $buyer->wallet->fresh()->balance)->toBe(760.00);
    expect(ContentPackPurchase::where('content_pack_id', $pack->id)->count())->toBe(1);
});
