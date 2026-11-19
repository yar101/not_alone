<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\UserIdolTrial;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServiceFeeAndRefundTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);
        $this->orderService = app(OrderService::class);

        PlatformSetting::set('order_customer_service_fee_percent', 5.0);
        PlatformSetting::set('platform_fee_percent', 10.0);
        PlatformSetting::set('rating_delta_order_refunded', -10.0);
    }

    public function test_order_payment_charges_service_price_and_five_percent_fee(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        // Customer deposits 1050
        $this->walletService->deposit($customer, 1050.00, 'Init', null, [], false);

        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Услуга 1000',
            'price' => 1000.00,
            'is_active' => true,
            'status' => 'approved',
        ]);

        [$order] = $this->orderService->createOrder($customer, $idol, [
            ['id' => $service->id, 'quantity' => 1],
        ]);

        $this->orderService->accept($order, $idol);

        // Pay order: 1000 service + 5% fee (50) = 1050 total
        $this->orderService->pay($order, $customer);

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
        $this->assertEquals(50.00, (float) $order->customer_service_fee);
        $this->assertEquals(5.00, (float) $order->customer_service_fee_percent);
        $this->assertEquals(1050.00, (float) $order->total_with_fee);

        $custWallet = $this->walletService->getOrCreateWallet($customer);
        // Balance was 1050, deducted 50 fee + 1000 hold -> available 0, held 1000
        $this->assertEquals(0.00, (float) $custWallet->balance);
        $this->assertEquals(1000.00, (float) $custWallet->held_balance);

        // Verify CustomerServiceFee transaction exists
        $feeTx = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::CustomerServiceFee)
            ->first();

        $this->assertNotNull($feeTx);
        $this->assertEquals(-50.00, (float) $feeTx->amount);
    }

    public function test_order_refund_returns_full_service_price_and_retains_service_fee(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Test',
            'email' => 'admin_fee_test@example.com',
            'password' => bcrypt('password'),
        ]);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true, 'rating' => 50.00]);

        $this->walletService->deposit($customer, 1050.00, 'Init', null, [], false);

        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Услуга 1000',
            'price' => 1000.00,
            'is_active' => true,
            'status' => 'approved',
        ]);

        [$order] = $this->orderService->createOrder($customer, $idol, [
            ['id' => $service->id, 'quantity' => 1],
        ]);

        $this->orderService->accept($order, $idol);
        $this->orderService->pay($order, $customer);

        // Open dispute
        $this->orderService->dispute($order, $customer, 'service_not_provided', str_repeat('Описание спора ', 10));

        $dispute = OrderDispute::where('order_id', $order->id)->first();

        // Admin approves dispute (transitioning to Refunded)
        $this->actingAs($admin, 'admin')->patchJson("/admin/disputes/{$dispute->id}/resolve", [
            'decision' => 'approved',
            'admin_note' => 'Возврат средств клиенту',
        ])->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::Refunded, $order->status);

        $custWallet = $this->walletService->getOrCreateWallet($customer);
        // Customer held balance reset to 0, available balance returned 1000 (100% of services)
        $this->assertEquals(0.00, (float) $custWallet->held_balance);
        $this->assertEquals(1000.00, (float) $custWallet->balance);

        // 50 service fee was retained by platform
        $this->assertEquals(50.00, (float) $order->customer_service_fee);

        // Idol rating penalized by -10.0 (from 50.00 to 40.00)
        $idol->refresh();
        $this->assertEquals(40.00, (float) $idol->rating);
    }

    public function test_trial_order_charges_zero_service_fee_and_restores_trial_on_dispute(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Trial',
            'email' => 'admin_trial@example.com',
            'password' => bcrypt('password'),
        ]);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Пробная услуга',
            'price' => 500.00,
            'is_trial' => true,
            'is_active' => true,
            'status' => 'approved',
        ]);

        [$order] = $this->orderService->createOrder($customer, $idol, [
            ['id' => $service->id, 'quantity' => 1],
        ]);

        $this->assertTrue(UserIdolTrial::where('order_id', $order->id)->exists());

        $this->orderService->accept($order, $idol);
        $this->orderService->pay($order, $customer);

        $order->refresh();
        $this->assertEquals(0.00, (float) $order->customer_service_fee);

        $this->orderService->dispute($order, $customer, 'service_not_provided', str_repeat('Описание спора ', 10));
        $dispute = OrderDispute::where('order_id', $order->id)->first();

        // Admin approves dispute
        $this->actingAs($admin, 'admin')->patchJson("/admin/disputes/{$dispute->id}/resolve", [
            'decision' => 'approved',
            'admin_note' => 'Возврат пробного заказа',
        ])->assertRedirect();

        // Verify UserIdolTrial was deleted (restoring customer's trial eligibility)
        $this->assertFalse(UserIdolTrial::where('order_id', $order->id)->exists());
    }

    public function test_platform_fee_percent_and_customer_fee_are_shared_with_inertia(): void
    {
        PlatformSetting::set('platform_fee_percent', 12.5);
        PlatformSetting::set('order_customer_service_fee_percent', 5.0);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('platform_fee_percent', 12.5)
                ->where('customer_service_fee_percent', fn ($v) => (float) $v === 5.0)
            );
    }
}
