<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WalletService;

test('order flow: hold with fee, completed into pink hold, and auto-closure into Closed with 422-FZ receipt', function () {
    PlatformSetting::set('order_customer_service_fee_percent', '5.0');
    PlatformSetting::set('platform_fee_percent', '12.0');
    PlatformSetting::set('order_dispute_window_minutes', '60');

    $customer = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create([
        'is_idol' => true,
        'tax_status' => 'self_employed',
        'inn' => '770123456789',
        'tax_verified_at' => now(),
        'rating' => 50.0,
    ]);

    $custWallet = $customer->getOrCreateWallet();
    $custWallet->forceFill(['balance' => 1050.00])->save();

    $category = ServiceCategory::create([
        'name' => ['ru' => 'Игры', 'en' => 'Games'],
        'accent_color' => '#ff00aa',
        'is_active' => true,
    ]);

    $timeUnit = ServiceTimeUnit::create([
        'name' => ['ru' => 'час', 'en' => 'hour'],
        'is_active' => true,
    ]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Совместная игра',
        'price' => 1000.00,
        'status' => 'approved',
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Pending,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'price' => 1000.00,
    ]);

    $walletService = app(WalletService::class);
    $orderService = app(OrderService::class);

    // 1. Pay order: 1000 held + 50 customer fee (5%)
    $orderService->accept($order, $idol);
    $orderService->pay($order, $customer);

    $custWallet->refresh();
    expect((float) $custWallet->balance)->toBe(0.00)
        ->and((float) $custWallet->held_balance)->toBe(1000.00);

    // 2. Complete order: moves into pink_held_balance (880 ₽ net)
    $orderService->confirmCompletion($order, $customer);

    $idolWallet = $idol->getOrCreateWallet()->fresh();
    $custWallet->refresh();

    expect((float) $custWallet->held_balance)->toBe(0.00)
        ->and((float) $idolWallet->pink_held_balance)->toBe(880.00)
        ->and((float) $idolWallet->pink_balance)->toBe(0.00)
        ->and($order->fresh()->status)->toBe(OrderStatus::Completed);

    // 3. Before 60 min: cannot release payout yet
    $earlyRelease = $walletService->releaseIdolPayout($order);
    expect($earlyRelease)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Completed);

    // 4. After 60 min: releaseIdolPayout transitions to Closed, moves 880 ₽ to pink_balance
    $this->travel(61)->minutes();

    $releasedTx = $walletService->releaseIdolPayout($order);
    expect($releasedTx)->not()->toBeNull();

    $idolWallet->refresh();
    $order->refresh();

    expect((float) $idolWallet->pink_held_balance)->toBe(0.00)
        ->and((float) $idolWallet->pink_balance)->toBe(880.00)
        ->and($order->status)->toBe(OrderStatus::Closed)
        ->and($order->payout_released_at)->not()->toBeNull();

    // Closed order cannot be disputed
    expect($order->status->canTransitionTo(OrderStatus::Disputed))->toBeFalse();
});

test('refund of completed order during 60-min hold returns 100% to customer and avoids negative balances', function () {
    PlatformSetting::set('order_customer_service_fee_percent', '5.0');
    PlatformSetting::set('platform_fee_percent', '12.0');
    PlatformSetting::set('rating_delta_order_refunded', '-10.0');

    $customer = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create([
        'is_idol' => true,
        'rating' => 50.0,
    ]);

    $custWallet = $customer->getOrCreateWallet();
    $custWallet->forceFill(['balance' => 1050.00])->save();

    $category = ServiceCategory::create([
        'name' => ['ru' => 'Игры', 'en' => 'Games'],
        'accent_color' => '#ff00aa',
        'is_active' => true,
    ]);

    $timeUnit = ServiceTimeUnit::create([
        'name' => ['ru' => 'час', 'en' => 'hour'],
        'is_active' => true,
    ]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Совместная игра',
        'price' => 1000.00,
        'status' => 'approved',
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
        'customer_service_fee' => 50.00,
        'customer_service_fee_percent' => 5.0,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'price' => 1000.00,
    ]);

    $walletService = app(WalletService::class);
    $orderService = app(OrderService::class);

    $orderService->pay($order, $customer);
    $orderService->confirmCompletion($order, $customer);

    $idolWallet = $idol->getOrCreateWallet()->fresh();
    expect((float) $idolWallet->pink_held_balance)->toBe(880.00)
        ->and((float) $idolWallet->pink_balance)->toBe(0.00);

    // Customer opens dispute during 60-min hold
    $orderService->dispute(
        $order,
        $customer,
        'Некачественная услуга',
        str_repeat('Подробное описание проблемы с заказом для соблюдения минимальной длины в 100 символов. ', 3)
    );
    expect($order->fresh()->status)->toBe(OrderStatus::Disputed);

    // Admin approves dispute -> Refunded
    $orderService->adminTransition($order, OrderStatus::Refunded, 1, 'Спор удовлетворён');

    $custWallet->refresh();
    $idolWallet->refresh();
    $idol->refresh();

    // Customer receives 100% services refund
    expect((float) $custWallet->balance)->toBe(1000.00)
        ->and((float) $custWallet->held_balance)->toBe(0.00);

    // Idol pink balance is strictly 0.00 (NO OVERDRAFT) and held is cancelled
    expect((float) $idolWallet->pink_balance)->toBe(0.00)
        ->and((float) $idolWallet->pink_held_balance)->toBe(0.00)
        ->and((float) $idol->rating)->toBe(40.13); // 50.0 + 0.13 (completed) - 10.0 (refunded)
});

test('convertPinkToBlue allows idols to convert pink coins to blue coins 1:1 and blocks non-idols', function () {
    $walletService = app(WalletService::class);

    $client = User::factory()->create(['is_idol' => false]);
    expect(function () use ($walletService, $client) {
        $walletService->convertPinkToBlue($client, 100.00);
    })->toThrow(\DomainException::class);

    $idol = User::factory()->create(['is_idol' => true]);
    $idolWallet = $idol->getOrCreateWallet();
    $idolWallet->forceFill(['pink_balance' => 500.00, 'balance' => 50.00])->save();

    $result = $walletService->convertPinkToBlue($idol, 200.00, 'conv_test_1');

    $idolWallet->refresh();
    expect((float) $idolWallet->pink_balance)->toBe(300.00)
        ->and((float) $idolWallet->balance)->toBe(250.00)
        ->and($result['pink_balance'])->toBe(300.00)
        ->and($result['balance'])->toBe(250.00);
});
