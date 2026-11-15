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

        PlatformSetting::set('order_customer_service_fee_percent', 5.0);
        PlatformSetting::set('refund_gateway_fee_percent', 4.0);
        PlatformSetting::set('refund_platform_fee_percent', 10.0);
        PlatformSetting::set('rating_delta_order_refunded', -10.0);
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

        $order = Order::create([
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

        return compact('customer', 'idol', 'order', 'service');
    }

    public function test_refund_with_explicit_fee_deduction(): void
    {
        $data = $this->createOrderScenario(10000.00, 15000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        // Customer pays 10,000 + 5% fee (500) = 10,500. Balance becomes 4,500, held becomes 10,000.
        $this->orderService->pay($order, $customer);
        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();

        $this->assertEquals(4500.00, (float) $customerWallet->balance);
        $this->assertEquals(10000.00, (float) $customerWallet->held_balance);

        // Refund hold with explicit fee deduction (4% gateway = 400, 10% platform = 1000, total fee = 1400)
        // Net refund = 10000 - 1400 = 8600
        $refundTx = $this->walletService->refundHold($order, 'Отмена заказа с вычетом', deductFees: true);

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
        $this->assertEquals(13100.00, (float) $customerWallet->balance); // 4500 + 8600 = 13100
    }

    public function test_default_refund_without_fee_deduction_returns_full_service_amount(): void
    {
        $data = $this->createOrderScenario(10000.00, 15000.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);

        // Default refund (deductFees: false)
        $refundTx = $this->walletService->refundHold($order, 'Возврат 100% услуг');

        $this->assertNotNull($refundTx);
        $this->assertEquals(10000.00, (float) $refundTx->amount);
        $this->assertEquals(0.00, (float) $refundTx->metadata['gateway_fee']);
        $this->assertEquals(0.00, (float) $refundTx->metadata['platform_fee']);
        $this->assertEquals(10000.00, (float) $refundTx->metadata['net_refund']);
        $this->assertFalse((bool) $refundTx->metadata['deduct_fees']);

        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();
        // 4500 remaining + 10000 refund = 14500 (retaining the 500 service fee)
        $this->assertEquals(14500.00, (float) $customerWallet->balance);
        $this->assertEquals(0.00, (float) $customerWallet->held_balance);
    }

    public function test_refund_calculation_handles_micro_amounts_and_rounding(): void
    {
        // 105 RUB order: with deductFees: true -> 4% gateway = 4.20, 10% platform = 10.50, total fee = 14.70, net = 90.30
        $data = $this->createOrderScenario(105, 500.00);
        $order = $data['order'];
        $customer = $data['customer'];

        $this->orderService->pay($order, $customer);

        $refundTx = $this->walletService->refundHold($order, 'Микро возврат', deductFees: true);
        $this->assertNotNull($refundTx);

        $this->assertEquals(105.00, (float) $refundTx->metadata['gross_amount']);
        $this->assertEquals(4.20, (float) $refundTx->metadata['gateway_fee']);
        $this->assertEquals(10.50, (float) $refundTx->metadata['platform_fee']);
        $this->assertEquals(90.30, (float) $refundTx->amount);
        $this->assertEquals(90.30, (float) $refundTx->metadata['net_refund']);

        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();
        $this->assertEquals(0.00, (float) $customerWallet->held_balance);
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
        $this->assertEquals(950.00, (float) $customerWallet->balance); // 2000 - 1050 = 950
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
        // 2000 - 1050 + 1000 = 1950
        $this->assertEquals(1950.00, (float) $customerWallet->balance);
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
            'order_customer_service_fee_percent' => 5.0,
            'refund_gateway_fee_percent' => 2.5,
            'refund_platform_fee_percent' => 8.0,
            'rating_deltas' => [
                'review_5star' => 0.8,
                'review_4star' => 0.4,
                'review_2star' => -0.5,
                'review_1star' => -1.2,
                'order_completed' => 0.2,
                'order_refunded' => -10.0,
                'report_accepted' => -2.0,
                'review_dispute_approved' => 0.6,
            ],
        ];

        $response = $this->actingAs($admin, 'admin')->patch('/admin/settings', $payload);
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Настройки сохранены.');

        $this->assertEquals(2.5, (float) PlatformSetting::get('refund_gateway_fee_percent'));
        $this->assertEquals(8.0, (float) PlatformSetting::get('refund_platform_fee_percent'));
    }

    public function test_refund_clawback_protects_against_idol_overdraft_limit(): void
    {
        $data = $this->createOrderScenario(10000.00, 20000.00);
        $order = $data['order'];
        $customer = $data['customer'];
        $idol = $data['idol'];

        $this->orderService->pay($order, $customer);

        // Idol completes order
        $this->orderService->confirmCompletion($order, $idol);
        $this->orderService->confirmCompletion($order, $customer);

        $order->refresh();
        $this->assertEquals(OrderStatus::Completed, $order->status);

        // Fast forward 60 minutes
        $this->walletService->releaseIdolPayout($order);

        $idolWallet = $this->walletService->getOrCreateWallet($idol)->fresh();
        // Idol received 9000 pink coins (10000 - 10% platform fee)
        $this->assertEquals(9000.00, (float) $idolWallet->pink_balance);

        // Set idol pink balance to -46,000 so clawback would breach -50,000 limit
        $idolWallet->forceFill(['pink_balance' => -46000.00])->save();

        // Perform refund
        $refundTx = $this->walletService->refundHold($order, 'Спор после завершения');
        $this->assertNotNull($refundTx);

        $idolWallet->refresh();
        // Idol capped at -50,000 overdraft limit
        $this->assertEquals(-50000.00, (float) $idolWallet->pink_balance);

        $clawbackTx = WalletTransaction::where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', WalletTransactionType::OrderClawback)
            ->first();

        $this->assertNotNull($clawbackTx);
        $this->assertEquals(4000.00, (float) $clawbackTx->metadata['clawback_amount']);
        $this->assertEquals(9000.00, (float) $clawbackTx->metadata['requested_clawback_amount']);

        // Customer was successfully refunded 100% of services (10000)
        $this->assertEquals(10000.00, (float) $refundTx->amount);
        $customerWallet = $this->walletService->getOrCreateWallet($customer)->fresh();
        $this->assertEquals(19500.00, (float) $customerWallet->balance); // 20000 - 10500 + 10000
    }
}
