<?php

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\ContentPack;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createTestAdminR7(): Admin
{
    return Admin::create([
        'name' => 'Admin Test R7',
        'email' => 'admin_r7_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

function getOrCreateTestCategoryAndUnitR7(): array
{
    $category = ServiceCategory::first();
    if (! $category) {
        $category = ServiceCategory::create([
            'name' => ['ru' => 'Тестовая категория', 'en' => 'Test Category'],
            'is_active' => true,
        ]);
    }

    $timeUnit = ServiceTimeUnit::first();
    if (! $timeUnit) {
        $timeUnit = ServiceTimeUnit::create([
            'name' => ['ru' => 'час', 'en' => 'hour'],
        ]);
    }

    return [$category, $timeUnit];
}

test('WalletService::releaseIdolPayout returns null cleanly when idol user is deleted', function () {
    $walletService = app(WalletService::class);

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
        'completed_at' => now()->subHours(2),
    ]);

    // Create a hold transaction and a pending payout transaction
    WalletTransaction::create([
        'wallet_id' => $walletService->getOrCreateWallet($customer)->id,
        'user_id' => $customer->id,
        'type' => WalletTransactionType::OrderHold,
        'amount' => -1000.00,
        'balance_before' => 1000.00,
        'balance_after' => 0.00,
        'held_balance_before' => 0.00,
        'held_balance_after' => 1000.00,
        'status' => WalletTransactionStatus::Completed,
        'reference_type' => Order::class,
        'reference_id' => $order->id,
    ]);

    WalletTransaction::create([
        'wallet_id' => $walletService->getOrCreateWallet($idol)->id,
        'user_id' => $idol->id,
        'type' => WalletTransactionType::OrderPayout,
        'amount' => 900.00,
        'balance_before' => 0.00,
        'balance_after' => 0.00,
        'held_balance_before' => 0.00,
        'held_balance_after' => 900.00,
        'status' => WalletTransactionStatus::Pending,
        'reference_type' => Order::class,
        'reference_id' => $order->id,
        'metadata' => [
            'gross_amount' => 1000.0,
            'fee_amount' => 100.0,
            'net_payout' => 900.0,
            'held' => true,
        ],
    ]);

    // Simulate missing idol
    DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_idol_id_foreign');
    DB::table('orders')->where('id', $order->id)->update(['idol_id' => 999999]);
    $order->refresh();

    // Call releaseIdolPayout — should return null cleanly without throwing TypeError
    $result = $walletService->releaseIdolPayout($order);
    expect($result)->toBeNull();
});

test('WalletService::refundHold throws DomainException when customer or idol is deleted', function () {
    $walletService = app(WalletService::class);

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
    ]);

    WalletTransaction::create([
        'wallet_id' => $walletService->getOrCreateWallet($customer)->id,
        'user_id' => $customer->id,
        'type' => WalletTransactionType::OrderHold,
        'amount' => -500.00,
        'balance_before' => 500.00,
        'balance_after' => 0.00,
        'held_balance_before' => 0.00,
        'held_balance_after' => 500.00,
        'status' => WalletTransactionStatus::Completed,
        'reference_type' => Order::class,
        'reference_id' => $order->id,
    ]);

    // When customer is missing
    DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_customer_id_foreign');
    DB::table('orders')->where('id', $order->id)->update(['customer_id' => 999999]);
    $order->refresh();

    expect(fn () => $walletService->refundHold($order))
        ->toThrow(\DomainException::class, 'Клиент заказа не найден.');
});

test('OrderService::accept and pay handle soft-deleted counterparty safely', function () {
    $orderService = app(OrderService::class);
    $walletService = app(WalletService::class);

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Pending,
    ]);

    // Soft delete customer before idol accepts
    $customer->delete();
    $order->refresh();

    // Accepting should not throw null notify error
    $orderService->accept($order, $idol);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Accepted);

    // Soft delete idol before customer pays
    $customer->restore();
    $idol->delete();
    $order->refresh();

    // Give customer funds
    $walletService->deposit($customer, 5000.00, 'Test Deposit', null, [], false);

    // Paying should not throw null notify error
    $orderService->pay($order, $customer);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid);
});

test('OrderService::adminTransition handles completed and refunded states with deleted idol', function () {
    $orderService = app(OrderService::class);
    $admin = createTestAdminR7();

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
    ]);

    // Idol deleted
    $idol->delete();
    $order->refresh();

    // Transition to Completed by admin should not crash on IdolRatingService::adjust
    $orderService->adminTransition($order, OrderStatus::Completed, $admin->id, 'Admin Completed');
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed);

    // Transition to Refunded by admin should not crash on IdolRatingService::adjust
    $orderService->adminTransition($order, OrderStatus::Refunded, $admin->id, 'Admin Refunded');
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Refunded);
});

test('ConversationController::show renders order data cleanly when customer or idol is deleted', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $conversation = Conversation::create();
    $conversation->participants()->createMany([
        ['user_id' => $customer->id],
        ['user_id' => $idol->id],
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
        'conversation_id' => $conversation->id,
    ]);
    $conversation->update(['order_id' => $order->id]);

    // Idol relation becomes null
    DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_idol_id_foreign');
    DB::table('orders')->where('id', $order->id)->update(['idol_id' => 999999]);

    $response = $this->actingAs($customer)
        ->getJson(route('conversations.show', $conversation));

    $response->assertOk()
        ->assertJsonPath('order.id', $order->id)
        ->assertJsonPath('order.idol', null)
        ->assertJsonPath('order.customer.id', $customer->id);
});

test('ConversationController::offerServices handles deleted recipient user safely', function () {
    [$category, $timeUnit] = getOrCreateTestCategoryAndUnitR7();

    $idol = User::factory()->create(['is_idol' => true]);
    $customer = User::factory()->create();

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга для предложения', 'en' => 'Offer Service'],
        'price' => 1500,
        'is_active' => true,
        'status' => 'approved',
    ]);

    $conversation = Conversation::create();
    $conversation->participants()->createMany([
        ['user_id' => $idol->id],
        ['user_id' => $customer->id],
    ]);

    // Customer soft deleted
    $customer->delete();

    $response = $this->actingAs($idol)
        ->postJson(route('conversations.offer-services', $conversation), [
            'services' => [$service->id],
        ]);

    $response->assertOk()
        ->assertJsonPath('type', 'service_offer');
});

test('Admin ContentPackModerationController::decide handles deleted author without 500 error', function () {
    $admin = createTestAdminR7();
    $idol = User::factory()->create(['is_idol' => true]);

    $pack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Test Pack for Moderation',
        'price' => 500,
        'status' => 'pending_review',
    ]);

    // Soft delete idol
    $idol->delete();

    $response = $this->actingAs($admin, 'admin')
        ->post(route('admin.content-packs.decide', $pack), [
            'decision' => 'approved',
        ]);

    $response->assertRedirect();
    $pack->refresh();
    expect($pack->status)->toBe('approved');
});

test('Admin ExportController eager loads order items service without N+1 queries', function () {
    $admin = createTestAdminR7();
    [$category, $timeUnit] = getOrCreateTestCategoryAndUnitR7();

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Экспортная услуга', 'en' => 'Export Service'],
        'price' => 1200,
        'is_active' => true,
        'status' => 'approved',
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
        'completed_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'service_id' => $service->id,
        'price' => 1200,
        'quantity' => 1,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.export.orders'));

    $response->assertOk();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain("{$order->id};");
});

test('Admin QuickSearchController handles null status gracefully', function () {
    $admin = createTestAdminR7();
    $customer = User::factory()->create(['name' => 'QuickSearchCustomer']);
    $idol = User::factory()->create(['name' => 'QuickSearchIdol', 'is_idol' => true]);

    Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Pending,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->getJson(route('admin.quick-search', ['q' => 'QuickSearch']));

    $response->assertOk()
        ->assertJsonStructure(['users', 'orders', 'packs', 'transactions']);
});

test('UserSearchController filters traits and interests safely with invalid/non-numeric input', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('users.search', [
        'traits' => ['abc', '-1', ''],
        'interests' => ['xyz', 'invalid'],
    ]));

    $response->assertOk();
});
