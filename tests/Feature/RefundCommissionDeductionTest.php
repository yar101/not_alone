<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RefundCommissionDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->walletService = app(WalletService::class);
        $this->orderService = app(OrderService::class);

        PlatformSetting::set('refund_gateway_fee_percent', 4.0);
        PlatformSetting::set('refund_platform_fee_percent', 10.0);
    }

    private function createOrderScenario(float $orderAmount = 10000.00, float $customerInitialBalance = 15000.00): array
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $customerWallet = $this->walletService->getOrCreateWallet($customer);
        $idolWallet = $this->walletService->getOrCreateWallet($idol);

        $customerWallet->forceFill(['balance' => $customerInitialBalance])->save();

        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Услуга',
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

        return compact('customer', 'idol', 'customerWallet', 'idolWallet', 'order', 'service');
    }

    public function test_refund_deducts_gateway_and_platform_fees_from_client_held_balance(): void
    {
        $data = $this->createOrderScenario(10000.00, 15000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        // Customer pays 10,000 -> 10,000 held in escrow
        $this->orderService->pay($order, $customer);
        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();

        $this->assertEquals(5000.00, (float) $customerWallet->balance);
        $this->assertEquals(10000.00, (float) $customerWallet->held_balance);

        // Refund hold with default fee deduction (4% gateway = 400, 10% platform = 1000, total fee = 1400)
        // Net refund = 10000 - 1400 = 8600
        $refundTx = $this->walletService->refundHold($order, 'Отмена заказа');

        $this->assertNotNull($refundTx);
        $this->assertEquals(WalletTransactionType::OrderRefund, $refundTx->type);
        $this->assertEquals(WalletTransactionStatus::Completed, $refundTx->status);
        $this->assertEquals(8600.00, (float) $refundTx->amount);

        // Metadata audit
        $metadata = $refundTx->metadata;
        $this->assertEquals(10000.00, (float) $metadata['gross_amount']);
        $this->assertEquals(400.00, (float) $metadata['gateway_fee']);
        $this->assertEquals(1000.00, (float) $metadata['platform_fee']);
        $this->assertEquals(8600.00, (float) $metadata['net_refund']);
        $this->assertEquals(4.0, (float) $metadata['refund_gateway_fee_percent']);
        $this->assertEquals(10.0, (float) $metadata['refund_platform_fee_percent']);
        $this->assertTrue((bool) $metadata['deduct_fees']);

        // Check customer wallet balances: held cleared completely, balance received net refund
        $customerWallet->refresh();
        $this->assertEquals(0.00, (float) $customerWallet->held_balance);
        $this->assertEquals(13600.00, (float) $customerWallet->balance); // 5000 + 8600 = 13600

        // Invariant check: sum(completed transactions) == balance
        $txSum = (float) WalletTransaction::where('wallet_id', $customerWallet->id)
            ->where('status', WalletTransactionStatus::Completed)
            ->sum('amount');
        // Initial deposit was not logged as transaction in forceFill, so change from initial (15000) is -1400:
        $this->assertEquals(-1400.00, $txSum);
        $this->assertEquals(15000.00 + $txSum, (float) $customerWallet->balance);
    }

    public function test_refund_without_fee_deduction_returns_full_amount(): void
    {
        $data = $this->createOrderScenario(10000.00, 15000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);

        // Refund with deductFees: false
        $refundTx = $this->walletService->refundHold($order, 'Возврат без комиссии', deductFees: false);

        $this->assertNotNull($refundTx);
        $this->assertEquals(10000.00, (float) $refundTx->amount);
        $this->assertEquals(0.00, (float) $refundTx->metadata['gateway_fee']);
        $this->assertEquals(0.00, (float) $refundTx->metadata['platform_fee']);
        $this->assertEquals(10000.00, (float) $refundTx->metadata['net_refund']);
        $this->assertFalse((bool) $refundTx->metadata['deduct_fees']);

        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();
        $this->assertEquals(15000.00, (float) $customerWallet->balance);
        $this->assertEquals(0.00, (float) $customerWallet->held_balance);
    }

    public function test_refund_calculation_handles_micro_amounts_and_rounding(): void
    {
        // 105 RUB order: 4% gateway = 4.20, 10% platform = 10.50, total fee = 14.70, net = 90.30
        $data = $this->createOrderScenario(105, 500.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);

        $refundTx = $this->walletService->refundHold($order, 'Микро возврат');
        $this->assertNotNull($refundTx);

        $this->assertEquals(105.00, (float) $refundTx->metadata['gross_amount']);
        $this->assertEquals(4.20, (float) $refundTx->metadata['gateway_fee']);
        $this->assertEquals(10.50, (float) $refundTx->metadata['platform_fee']);
        $this->assertEquals(90.30, (float) $refundTx->amount);
        $this->assertEquals(90.30, (float) $refundTx->metadata['net_refund']);

        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();
        $this->assertEquals(0.00, (float) $customerWallet->held_balance);
        $this->assertEquals(485.30, (float) $customerWallet->balance); // 500 - 105 + 90.30
    }

    public function test_refund_cannot_result_in_negative_amount_when_fees_exceed_100(): void
    {
        $data = $this->createOrderScenario(1000.00, 2000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);

        // Custom fees: 60% gateway + 60% platform = 120%
        $refundTx = $this->walletService->refundHold(
            $order,
            'Экстремальные комиссии',
            deductFees: true,
            customGatewayFeePercent: 60.0,
            customPlatformFeePercent: 60.0
        );

        $this->assertNotNull($refundTx);
        $this->assertEquals(0.00, (float) $refundTx->amount);
        $this->assertEquals(0.00, (float) $refundTx->metadata['net_refund']);

        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();
        $this->assertEquals(0.00, (float) $customerWallet->held_balance);
        $this->assertEquals(1000.00, (float) $customerWallet->balance); // didn't go below remaining balance
    }

    public function test_refund_hold_is_idempotent_under_multiple_calls(): void
    {
        $data = $this->createOrderScenario(1000.00, 2000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);

        $tx1 = $this->walletService->refundHold($order, 'Первый возврат');
        $this->assertNotNull($tx1);

        $tx2 = $this->walletService->refundHold($order, 'Повторный возврат');
        $this->assertNotNull($tx2);
        $this->assertEquals($tx1->id, $tx2->id);

        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();
        // 4% + 10% = 14% = 140 RUB fee, net refund = 860 RUB -> balance = 1000 + 860 = 1860
        $this->assertEquals(1860.00, (float) $customerWallet->balance);
        $this->assertEquals(0.00, (float) $customerWallet->held_balance);

        // Only one refund transaction in database
        $count = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::OrderRefund)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_admin_can_update_refund_fee_percentages_in_settings(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Fees',
            'email' => 'admin_fees@example.com',
            'password' => bcrypt('password'),
        ]);

        $payload = [
            'rating_low_threshold' => 30,
            'order_auto_complete_delay' => 72,
            'content_pack_price_min' => 100,
            'content_pack_price_max' => 10000,
            'moderate_new_packs' => true,
            'moderate_existing_packs' => false,
            'deposit_fee_percent' => 4.5,
            'withdrawal_fee_percent' => 3.5,
            'platform_fee_percent' => 12.0,
            'refund_gateway_fee_percent' => 2.5,
            'refund_platform_fee_percent' => 8.0,
            'rating_deltas' => [
                'review_5star' => 0.8,
                'review_4star' => 0.4,
                'review_2star' => -0.5,
                'review_1star' => -1.2,
                'order_completed' => 0.2,
                'report_accepted' => -2.0,
                'review_dispute_approved' => 0.6,
            ],
        ];

        $response = $this->actingAs($admin, 'admin')->patch('/admin/settings', $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Настройки сохранены.');

        $this->assertEquals(2.5, (float) PlatformSetting::get('refund_gateway_fee_percent'));
        $this->assertEquals(8.0, (float) PlatformSetting::get('refund_platform_fee_percent'));

        // Validation: reject negative values
        $invalidPayload = $payload;
        $invalidPayload['refund_gateway_fee_percent'] = -5;
        $invalidResponse = $this->actingAs($admin, 'admin')->patch('/admin/settings', $invalidPayload);
        $invalidResponse->assertSessionHasErrors(['refund_gateway_fee_percent']);

        // Validation: reject values > 50
        $invalidPayload2 = $payload;
        $invalidPayload2['refund_platform_fee_percent'] = 55;
        $invalidResponse2 = $this->actingAs($admin, 'admin')->patch('/admin/settings', $invalidPayload2);
        $invalidResponse2->assertSessionHasErrors(['refund_platform_fee_percent']);
    }

    public function test_refund_clawback_protects_against_idol_overdraft_limit(): void
    {
        $data = $this->createOrderScenario(10000.00, 20000.00);
        $order = $data['order'];
        $customer = $data['customer'];
        $idol = $data['idol'];

        $this->orderService->pay($order, $customer);
        $this->orderService->confirmCompletion($order, $customer);

        // Release payout to idol (net payout after 10% fee = 9000)
        $this->walletService->releaseIdolPayout($order);

        // Put idol's balance near the lower limit of -50,000 RUB (e.g. -45,000)
        $idolWallet = $this->walletService->getOrCreateWallet($idol);
        $idolWallet->forceFill(['balance' => -45000.00])->save();

        // Customer disputes and gets refunded
        // Clawback is 9000 RUB. -45000 - 9000 = -54000, which exceeds -50000 limit.
        // Capping mechanism must stop idol balance at -50,000 without crashing DB check constraint.
        $refundTx = $this->walletService->refundHold($order, 'Возврат по спору');
        $this->assertNotNull($refundTx);

        $idolWallet->refresh();
        $this->assertEquals(-50000.00, (float) $idolWallet->balance);

        // Clawback transaction recorded with capped amount (5000 instead of 9000)
        $clawbackTx = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::OrderClawback)
            ->first();

        $this->assertNotNull($clawbackTx);
        $this->assertEquals(-5000.00, (float) $clawbackTx->amount);
        $this->assertTrue((bool) $clawbackTx->metadata['overdraft_capped']);
        $this->assertEquals(5000.00, (float) $clawbackTx->metadata['clawback_amount']);
        $this->assertEquals(9000.00, (float) $clawbackTx->metadata['requested_clawback_amount']);

        // Customer was successfully refunded net amount (8600)
        $this->assertEquals(8600.00, (float) $refundTx->amount);
        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();
        $this->assertEquals(18600.00, (float) $customerWallet->balance); // 10000 remaining + 8600
    }

    public function test_transaction_controller_aggregates_refund_platform_fees(): void
    {
        $data = $this->createOrderScenario(10000.00, 20000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);
        $this->walletService->refundHold($order, 'Отмена с комиссией');

        $admin = Admin::create([
            'name' => 'Admin Analytics',
            'email' => 'admin_analytics@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/admin/transactions');
        $response->assertOk();

        // KPI total_fees must include platform_fee (1000.00) from the refund
        $kpi = $response->viewData('page')['props']['kpi'];
        $this->assertGreaterThanOrEqual(1000.00, (float) $kpi['total_fees']);
    }
}
