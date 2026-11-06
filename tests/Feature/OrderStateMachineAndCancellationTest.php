<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
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
use Tests\TestCase;

class OrderStateMachineAndCancellationTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;

    private WalletService $walletService;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.payments.mock_purchases' => false]);

        $this->orderService = app(OrderService::class);
        $this->walletService = app(WalletService::class);

        $this->admin = Admin::create([
            'name' => 'Admin FSM',
            'email' => 'admin_fsm_'.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);
    }

    private function createOrderScenario(float $price = 1000.00, float $customerDeposit = 3000.00): array
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($customer, $customerDeposit, 'Deposit', null, [], false);

        $category = ServiceCategory::create(['name' => 'Категория_'.uniqid()]);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час_'.uniqid()]);

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Услуга_'.uniqid(),
            'price' => $price,
            'is_active' => true,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Accepted,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'price' => $price,
            'quantity' => 1,
        ]);

        return compact('customer', 'idol', 'service', 'order');
    }

    public function test_customer_receives_403_when_cancelling_paid_order_via_http(): void
    {
        $data = $this->createOrderScenario(1000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);
        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);

        $response = $this->actingAs($customer)->patchJson("/orders/{$order->id}/cancel", [
            'cancel_reason' => 'Changed my mind',
        ]);

        $response->assertStatus(403);
        $this->assertEquals(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_customer_receives_domain_exception_when_cancelling_paid_order_via_service(): void
    {
        $data = $this->createOrderScenario(1000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);
        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Отмена оплаченного заказа клиентом не разрешена. Откройте спор, если услуга не была оказана.');

        $this->orderService->cancel($order, $customer, 'Want my money back');
    }

    public function test_idol_can_cancel_paid_order_with_automatic_refund_to_customer(): void
    {
        $data = $this->createOrderScenario(1500.00, 3000.00);
        $order = $data['order'];
        $customer = $data['customer'];
        $idol = $data['idol'];

        // Customer pays 1500 -> held in escrow
        $this->orderService->pay($order, $customer);
        $custWallet = $this->walletService->getOrCreateWallet($customer);
        $this->assertEquals(1500.00, (float) $custWallet->fresh()->held_balance);
        $this->assertEquals(1500.00, (float) $custWallet->fresh()->balance); // 3000 deposit - 1500 held

        // Idol cancels the paid order
        $this->orderService->cancel($order, $idol, 'Idol is unavailable');
        $order->refresh();

        $this->assertEquals(OrderStatus::Cancelled, $order->status);
        $this->assertEquals($idol->id, $order->cancelled_by);
        $this->assertEquals('Idol is unavailable', $order->cancel_reason);

        // Funds refunded to customer with fees deducted (4% gateway + 10% platform = 210 RUB fee)
        // Net refund: 1500 - 210 = 1290 RUB. Customer balance: 1500 remaining + 1290 = 2790 RUB.
        $custWallet->refresh();
        $this->assertEquals(0.00, (float) $custWallet->held_balance);
        $this->assertEquals(2790.00, (float) $custWallet->balance);

        // Verify OrderRefund transaction exists
        $refundTx = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::OrderRefund)
            ->first();

        $this->assertNotNull($refundTx);
        $this->assertEquals(1290.00, (float) $refundTx->amount);
        $this->assertEquals(WalletTransactionStatus::Completed, $refundTx->status);
    }

    public function test_customer_can_cancel_pending_and_accepted_orders(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        // 1. Pending order
        $pendingOrder = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Pending,
        ]);
        $this->orderService->cancel($pendingOrder, $customer, 'Cancelled while pending');
        $this->assertEquals(OrderStatus::Cancelled, $pendingOrder->fresh()->status);

        // 2. Accepted order
        $acceptedOrder = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Accepted,
        ]);
        $this->orderService->cancel($acceptedOrder, $customer, 'Cancelled while accepted');
        $this->assertEquals(OrderStatus::Cancelled, $acceptedOrder->fresh()->status);
    }

    public function test_admin_cannot_transition_cancelled_order_to_any_status(): void
    {
        $data = $this->createOrderScenario();
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->cancel($order, $customer, 'Cancelled accepted order');
        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);

        $targets = [
            OrderStatus::Pending,
            OrderStatus::Accepted,
            OrderStatus::Paid,
            OrderStatus::Completed,
            OrderStatus::Refunded,
            OrderStatus::Disputed,
            OrderStatus::Cancelled,
        ];

        foreach ($targets as $target) {
            try {
                $this->orderService->adminTransition($order, $target, $this->admin->id, 'Attempt from cancelled');
                $this->fail("Expected DomainException transitioning cancelled to {$target->value}");
            } catch (\DomainException $e) {
                $this->assertEquals("Недопустимый переход статуса заказа из cancelled в {$target->value}.", $e->getMessage());
            }
        }
    }

    public function test_admin_cannot_transition_refunded_order_to_paid_or_completed(): void
    {
        $data = $this->createOrderScenario(1000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);
        $this->orderService->adminTransition($order, OrderStatus::Refunded, $this->admin->id, 'Admin refund');
        $this->assertEquals(OrderStatus::Refunded, $order->fresh()->status);

        // Cannot transition from Refunded to Paid
        try {
            $this->orderService->adminTransition($order, OrderStatus::Paid, $this->admin->id, 'Reopen to paid');
            $this->fail('Expected DomainException transitioning refunded to paid');
        } catch (\DomainException $e) {
            $this->assertEquals('Недопустимый переход статуса заказа из refunded в paid.', $e->getMessage());
        }

        // Cannot transition from Refunded to Completed
        try {
            $this->orderService->adminTransition($order, OrderStatus::Completed, $this->admin->id, 'Reopen to completed');
            $this->fail('Expected DomainException transitioning refunded to completed');
        } catch (\DomainException $e) {
            $this->assertEquals('Недопустимый переход статуса заказа из refunded в completed.', $e->getMessage());
        }
    }

    public function test_admin_cannot_transition_completed_order_to_paid(): void
    {
        $data = $this->createOrderScenario(1000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);
        $this->orderService->confirmCompletion($order, $customer);
        $this->assertEquals(OrderStatus::Completed, $order->fresh()->status);

        try {
            $this->orderService->adminTransition($order, OrderStatus::Paid, $this->admin->id, 'Reopen completed to paid');
            $this->fail('Expected DomainException transitioning completed to paid');
        } catch (\DomainException $e) {
            $this->assertEquals('Недопустимый переход статуса заказа из completed в paid.', $e->getMessage());
        }
    }

    public function test_admin_cannot_transition_pending_or_accepted_to_refunded_without_payment(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        // Pending -> Refunded is blocked
        $pendingOrder = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Pending,
        ]);

        try {
            $this->orderService->adminTransition($pendingOrder, OrderStatus::Refunded, $this->admin->id, 'Refund pending');
            $this->fail('Expected DomainException transitioning pending to refunded');
        } catch (\DomainException $e) {
            $this->assertEquals('Недопустимый переход статуса заказа из pending в refunded.', $e->getMessage());
        }

        // Accepted -> Refunded is blocked
        $acceptedOrder = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Accepted,
        ]);

        try {
            $this->orderService->adminTransition($acceptedOrder, OrderStatus::Refunded, $this->admin->id, 'Refund accepted');
            $this->fail('Expected DomainException transitioning accepted to refunded');
        } catch (\DomainException $e) {
            $this->assertEquals('Недопустимый переход статуса заказа из accepted в refunded.', $e->getMessage());
        }
    }

    public function test_release_hold_does_not_release_payout_if_order_has_order_refund(): void
    {
        $data = $this->createOrderScenario(1000.00);
        $order = $data['order'];
        $customer = $data['customer'];
        $idol = $data['idol'];

        // Customer pays -> OrderHold created
        $this->orderService->pay($order, $customer);
        $custWallet = $this->walletService->getOrCreateWallet($customer);

        $holdTx = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::OrderHold)
            ->latest('id')
            ->first();
        $this->assertNotNull($holdTx);

        // Manually record an OrderRefund after the hold
        WalletTransaction::create([
            'wallet_id' => $custWallet->id,
            'user_id' => $customer->id,
            'type' => WalletTransactionType::OrderRefund,
            'amount' => 1000.00,
            'balance_before' => $custWallet->balance,
            'balance_after' => (float) bcadd((string) $custWallet->balance, '1000.00', 2),
            'held_balance_before' => $custWallet->held_balance,
            'held_balance_after' => 0.00,
            'status' => WalletTransactionStatus::Completed,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'description' => 'Manual refund test',
        ]);

        // Attempt to release hold -> must return empty array without creating payout/fee
        $result = $this->walletService->releaseHold($order);
        $this->assertEmpty($result);

        $payoutExists = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::OrderPayout)
            ->exists();
        $this->assertFalse($payoutExists);

        $feeExists = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::PlatformFee)
            ->exists();
        $this->assertFalse($feeExists);
    }
}
