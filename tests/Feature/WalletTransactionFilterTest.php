<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\ContentPack;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Cache::flush();
    PlatformSetting::set('platform_fee_percent', 20.0);
    PlatformSetting::set('order_customer_service_fee_percent', 5.0);
    PlatformSetting::set('order_dispute_window_minutes', 60);
});

function makeWalletFilterOrder(float $orderAmount = 1000.00, float $customerBalance = 2000.00): array
{
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $customerWallet = $walletService->getOrCreateWallet($customer);
    $idolWallet = $walletService->getOrCreateWallet($idol);
    $customerWallet->forceFill(['balance' => $customerBalance])->save();

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

it('holds filter shows only hold lifecycle rows, not refunds or fees', function () {
    $data = makeWalletFilterOrder(1000.00, 2000.00);
    $orderService = app(OrderService::class);

    // CustomerServiceFee (-50) + OrderHold (-1000)
    $orderService->pay($data['order'], $data['customer']);

    // Paid order cancellation -> refundHold unfreeze+credit row (OrderRefund)
    $admin = Admin::create(['name' => 'AdminFilter', 'email' => 'admin_filter@test.local', 'password' => 'secret']);
    $orderService->adminTransition($data['order'], OrderStatus::Cancelled, $admin->id, 'Отмена для теста фильтров');

    $response = $this->actingAs($data['customer'])->getJson('/api/wallet?filter=holds');
    $response->assertOk();
    $types = array_column($response->json('transactions'), 'type');

    expect($types)->toContain('order_hold')
        ->and($types)->not->toContain('order_refund')
        ->and($types)->not->toContain('customer_service_fee')
        ->and($types)->not->toContain('platform_fee');

    $ordersResponse = $this->actingAs($data['customer'])->getJson('/api/wallet?filter=orders');
    $ordersResponse->assertOk();
    $ordersTypes = array_column($ordersResponse->json('transactions'), 'type');

    expect($ordersTypes)->toContain('order_hold')
        ->and($ordersTypes)->toContain('order_refund')
        ->and($ordersTypes)->toContain('customer_service_fee');
});

it('holds filter keeps pending payout but drops completed payout and platform fee', function () {
    $data = makeWalletFilterOrder(1000.00, 2000.00);
    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    $orderService->pay($data['order'], $data['customer']);
    // releaseHold: OrderHoldRelease (customer) + pending OrderPayout + pending PlatformFee (idol)
    $orderService->confirmCompletion($data['order'], $data['customer']);

    $pending = $this->actingAs($data['idol'])->getJson('/api/wallet?filter=holds');
    $pending->assertOk();
    $pendingTypes = array_column($pending->json('transactions'), 'type');

    expect($pendingTypes)->toContain('order_payout')
        ->and($pendingTypes)->not->toContain('platform_fee');

    $data['order']->forceFill(['completed_at' => now()->subMinutes((int) PlatformSetting::get('order_dispute_window_minutes', 60) + 1)])->save();
    $walletService->releaseIdolPayout($data['order']);

    $released = $this->actingAs($data['idol'])->getJson('/api/wallet?filter=holds');
    $released->assertOk();
    $releasedTypes = array_column($released->json('transactions'), 'type');
    expect($releasedTypes)->not->toContain('order_payout');

    $orders = $this->actingAs($data['idol'])->getJson('/api/wallet?filter=orders');
    $orders->assertOk();
    expect(array_column($orders->json('transactions'), 'type'))->toContain('order_payout');
});

it('packs filter includes pack sale and its platform fee, orders filter does not', function () {
    $seller = User::factory()->create(['is_idol' => true]);
    $buyer = User::factory()->create();

    $pack = ContentPack::create([
        'user_id' => $seller->id,
        'title' => 'Тестовый пак',
        'description' => 'Описание пака',
        'price' => 500,
        'status' => 'published',
    ]);

    // testing env auto-deposits buyer balance inside purchaseContentPack
    app(WalletService::class)->purchaseContentPack($buyer, $pack);

    $packs = $this->actingAs($seller)->getJson('/api/wallet?filter=packs');
    $packs->assertOk();
    $packsTypes = array_column($packs->json('transactions'), 'type');

    expect($packsTypes)->toContain('pack_sale')
        ->and($packsTypes)->toContain('platform_fee');

    $orders = $this->actingAs($seller)->getJson('/api/wallet?filter=orders');
    $orders->assertOk();
    expect(array_column($orders->json('transactions'), 'type'))->not->toContain('platform_fee');

    $buyerPacks = $this->actingAs($buyer)->getJson('/api/wallet?filter=packs');
    $buyerPacks->assertOk();
    expect(array_column($buyerPacks->json('transactions'), 'type'))->toContain('pack_purchase');
});
