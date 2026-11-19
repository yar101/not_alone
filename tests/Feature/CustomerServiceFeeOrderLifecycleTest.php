<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServiceFeeOrderLifecycleTest extends TestCase
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
    }

    public function test_fee_is_calculated_immediately_on_order_creation(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $category = ServiceCategory::create(['name' => 'Test Cat']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Услуга 2000',
            'price' => 2000.00,
            'is_active' => true,
            'status' => 'approved',
        ]);

        [$order, $conversation] = $this->orderService->createOrder($customer, $idol, [
            ['id' => $service->id, 'quantity' => 1],
        ]);

        $order->refresh();

        // 1. Order is pending but fee is already calculated and stored
        $this->assertEquals(OrderStatus::Pending, $order->status);
        $this->assertEquals(100.00, (float) $order->customer_service_fee); // 5% of 2000 = 100
        $this->assertEquals(5.00, (float) $order->customer_service_fee_percent);
        $this->assertEquals(2100.00, (float) $order->total_with_fee);

        // 2. System message order_created contains fee in metadata
        $msg = $conversation->messages()->where('type', 'system')->first();
        $this->assertNotNull($msg);
        $this->assertEquals('order_created', $msg->metadata['event']);
        $this->assertEquals(100.00, (float) $msg->metadata['service_fee']);
        $this->assertEquals(5.00, (float) $msg->metadata['fee_percent']);
        $this->assertEquals(2000.00, (float) $msg->metadata['service_price']);
        $this->assertEquals(2100.00, (float) $msg->metadata['total_price']);
    }

    public function test_adding_item_recalculates_fee_and_broadcasts_updated_receipt(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $category = ServiceCategory::create(['name' => 'Test Cat 2']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $service1 = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Услуга 1000',
            'price' => 1000.00,
            'is_active' => true,
            'status' => 'approved',
        ]);

        $service2 = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Услуга 2000',
            'price' => 2000.00,
            'is_active' => true,
            'status' => 'approved',
        ]);

        [$order, $conversation] = $this->orderService->createOrder($customer, $idol, [
            ['id' => $service1->id, 'quantity' => 1],
        ]);

        $this->assertEquals(50.00, (float) $order->fresh()->customer_service_fee);

        // Now idol offers additional service and it is added to the order
        $this->orderService->addItem($order, $service2->id, $customer);

        $order->refresh();

        // Total service price: 1000 + 2000 = 3000. Fee: 5% of 3000 = 150. Total with fee: 3150.
        $this->assertEquals(150.00, (float) $order->customer_service_fee);
        $this->assertEquals(5.00, (float) $order->customer_service_fee_percent);
        $this->assertEquals(3150.00, (float) $order->total_with_fee);

        // System message item_added contains updated fee metadata
        $itemAddedMsg = $conversation->messages()->latest('id')->first();
        $this->assertNotNull($itemAddedMsg);
        $this->assertEquals('item_added', $itemAddedMsg->metadata['event']);
        $this->assertEquals(150.00, (float) $itemAddedMsg->metadata['service_fee']);
        $this->assertEquals(5.00, (float) $itemAddedMsg->metadata['fee_percent']);
        $this->assertEquals(3000.00, (float) $itemAddedMsg->metadata['service_price']);
        $this->assertEquals(3150.00, (float) $itemAddedMsg->metadata['total_price']);

        // Now customer pays the order
        $this->walletService->deposit($customer, 3150.00, 'Init deposit', null, [], false);
        $this->orderService->accept($order, $idol);
        $this->orderService->pay($order, $customer);

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
        $this->assertEquals(150.00, (float) $order->customer_service_fee);

        $feeTx = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::CustomerServiceFee)
            ->first();
        $this->assertNotNull($feeTx);
        $this->assertEquals(-150.00, (float) $feeTx->amount);
    }

    public function test_admin_marks_order_as_paid_charges_customer_service_fee(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $category = ServiceCategory::create(['name' => 'Test Cat admin paid']);
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

        $this->walletService->deposit($customer, 2000.00, 'Init deposit', null, [], false);
        $this->orderService->accept($order, $idol);

        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin_adminpaid@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->orderService->adminTransition($order, OrderStatus::Paid, $admin->id, null);

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
        $this->assertEquals(50.00, (float) $order->customer_service_fee);
        $this->assertEquals(5.00, (float) $order->customer_service_fee_percent);

        $wallet = $customer->wallet->fresh();
        $this->assertEquals(950.00, (float) $wallet->balance);
        $this->assertEquals(1000.00, (float) $wallet->held_balance);

        $feeTx = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::CustomerServiceFee)
            ->first();
        $this->assertNotNull($feeTx);
        $this->assertEquals(-50.00, (float) $feeTx->amount);
    }
}
