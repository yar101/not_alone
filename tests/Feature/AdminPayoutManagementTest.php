<?php

namespace Tests\Feature;

use App\Contracts\MandarinGatewayInterface;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutBatch;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\PayoutManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    // Default setting: auto weekly payouts are OFF by default
    PlatformSetting::set('weekly_payouts_auto_enabled', '0');
    PlatformSetting::set('payout_min_threshold', '1000.00');

    // Mock Mandarin Gateway
    $gatewayMock = \Mockery::mock(MandarinGatewayInterface::class);
    $gatewayMock->shouldReceive('executePayout')->byDefault()->andReturn([
        'payout_id' => 'gw_payout_12345',
        'operation_id' => 'gw_op_12345',
        'status' => 'settled',
        'fns_receipt_url' => 'https://npd.nalog.ru/receipt/12345',
    ]);
    $gatewayMock->shouldReceive('getOperationStatus')->byDefault()->andReturn([
        'operation_id' => 'gw_op_12345',
        'status' => 'settled',
        'raw' => [],
    ]);
    app()->instance(MandarinGatewayInterface::class, $gatewayMock);
});

function createAdminUser(): Admin
{
    return Admin::create([
        'name' => 'Admin Boss',
        'email' => 'admin@notalone.local',
        'password' => bcrypt('password'),
    ]);
}

function createReadySelfEmployedIdol(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'is_idol' => true,
        'tax_status' => 'self_employed',
        'inn' => '770123456789',
        'is_payout_ready' => true,
        'payout_phone' => '+79991234567',
        'sbp_bank_id' => 'tinkoff',
        'payout_requisites_changed_at' => now()->subDays(5),
        'created_at' => now()->subDays(10),
    ], $attributes));

    $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);
    $wallet->forceFill([
        'is_active' => true,
        'pink_balance' => 2500.00,
        'pink_held_balance' => 0.00,
    ])->save();

    return $user;
}

it('restricts guests from viewing payouts page', function () {
    $response = $this->get(route('admin.payouts.index'));
    $response->assertRedirect(route('admin.login'));
});

it('allows admin to view payouts page with queue and preview', function () {
    $admin = createAdminUser();
    $idol = createReadySelfEmployedIdol();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.payouts.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Payouts/Index')
        ->has('preview')
        ->where('preview.auto_payout_enabled', false)
        ->has('preview.idols', 1)
        ->where('preview.idols.0.name', $idol->name)
        ->has('batches')
        ->has('transactions')
    );
});

it('defaults auto payouts to false and allows admin to toggle on and off', function () {
    $admin = createAdminUser();

    // Verify default in service is false
    $payoutService = app(PayoutManagementService::class);
    expect($payoutService->getAutoPayoutEnabled())->toBeFalse();

    // Toggle ON
    $response = $this->actingAs($admin, 'admin')->post(route('admin.payouts.toggle-auto'), [
        'enabled' => true,
    ]);

    $response->assertSessionHas('success');
    expect($payoutService->getAutoPayoutEnabled())->toBeTrue();

    // Toggle OFF
    $response = $this->actingAs($admin, 'admin')->post(route('admin.payouts.toggle-auto'), [
        'enabled' => false,
    ]);

    $response->assertSessionHas('success');
    expect($payoutService->getAutoPayoutEnabled())->toBeFalse();
});

it('creates draft batch when weekly payouts command runs with auto disabled', function () {
    $idol = createReadySelfEmployedIdol(['name' => 'Alice Star']);

    // Ensure auto is disabled
    PlatformSetting::set('weekly_payouts_auto_enabled', '0');

    Artisan::call('orders:schedule-weekly-payouts');

    // Batch must be created in pending_approval status
    $batch = PayoutBatch::where('status', 'pending_approval')->first();
    expect($batch)->not->toBeNull();
    expect((float) $batch->total_amount)->toBe(2500.00);
    expect($batch->total_recipients)->toBe(1);
    expect($batch->is_automated)->toBeFalse();

    // Idol funds must NOT be deducted yet!
    $wallet = Wallet::where('user_id', $idol->id)->first();
    expect((float) $wallet->pink_balance)->toBe(2500.00);
    expect((float) $wallet->pink_held_balance)->toBe(0.00);
});

it('automatically dispatches payouts when auto mode is enabled', function () {
    $idol = createReadySelfEmployedIdol(['name' => 'Bob Idol']);

    // Enable auto mode
    PlatformSetting::set('weekly_payouts_auto_enabled', '1');

    Artisan::call('orders:schedule-weekly-payouts');

    // Batch must be created and completed
    $batch = PayoutBatch::latest()->first();
    expect($batch)->not->toBeNull();
    expect($batch->status)->toBe('completed');
    expect($batch->successful_count)->toBe(1);
    expect($batch->is_automated)->toBeTrue();

    // Idol balance must be updated
    $wallet = Wallet::where('user_id', $idol->id)->first();
    expect((float) $wallet->pink_balance)->toBe(0.00);
    expect((float) $wallet->pink_held_balance)->toBe(0.00);

    // Wallet transaction recorded
    $tx = WalletTransaction::where('user_id', $idol->id)->where('type', WalletTransactionType::Withdrawal)->first();
    expect($tx)->not->toBeNull();
    expect($tx->status)->toBe(WalletTransactionStatus::Completed);
});

it('allows admin to dispatch batch manually', function () {
    $admin = createAdminUser();
    $idol = createReadySelfEmployedIdol();

    // Create a pending batch
    $payoutService = app(PayoutManagementService::class);
    $batch = $payoutService->createPendingBatch(isAutomated: false);
    expect($batch->status)->toBe('pending_approval');

    $response = $this->actingAs($admin, 'admin')->post(route('admin.payouts.dispatch-batch'), [
        'batch_id' => $batch->id,
    ]);

    $response->assertSessionHas('success');

    $batch->refresh();
    expect($batch->status)->toBe('completed');
    expect($batch->approved_by)->toBe($admin->id);
    expect($batch->successful_count)->toBe(1);
});

it('allows admin to dispatch out-of-turn payout for an idol even under 1000 rub', function () {
    $admin = createAdminUser();
    $idol = createReadySelfEmployedIdol();

    // Set balance under 1 000 ₽ (e.g. 450 ₽)
    $wallet = Wallet::where('user_id', $idol->id)->first();
    $wallet->forceFill(['pink_balance' => 450.00])->save();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.payouts.dispatch-single', $idol->id), [
        'amount' => 450.00,
        'bypass_anti_fraud' => false,
    ]);

    $response->assertSessionHas('success');

    $wallet->refresh();
    expect((float) $wallet->pink_balance)->toBe(0.00);

    $tx = WalletTransaction::where('user_id', $idol->id)->where('type', WalletTransactionType::Withdrawal)->first();
    expect($tx)->not->toBeNull();
    expect($tx->metadata['is_out_of_turn'])->toBeTrue();
    expect($tx->metadata['dispatched_by_admin_id'])->toBe($admin->id);
});

it('requires anti-fraud bypass confirmation when requisites changed recently', function () {
    $admin = createAdminUser();
    $idol = createReadySelfEmployedIdol([
        'payout_requisites_changed_at' => now()->subHours(2), // Changed 2 hours ago!
    ]);

    // Attempting without bypass should fail
    $response = $this->actingAs($admin, 'admin')->post(route('admin.payouts.dispatch-single', $idol->id), [
        'amount' => 500.00,
        'bypass_anti_fraud' => false,
    ]);

    $response->assertSessionHas('error');

    // Attempting with bypass_anti_fraud: true should succeed
    $responseSuccess = $this->actingAs($admin, 'admin')->post(route('admin.payouts.dispatch-single', $idol->id), [
        'amount' => 500.00,
        'bypass_anti_fraud' => true,
    ]);

    $responseSuccess->assertSessionHas('success');

    $wallet = Wallet::where('user_id', $idol->id)->first();
    expect((float) $wallet->pink_balance)->toBe(2000.00);
});

it('provides itemized breakdown for idol earnings', function () {
    $admin = createAdminUser();
    $idol = createReadySelfEmployedIdol();
    $customer = User::factory()->create(['name' => 'Charlie Client']);

    $category = ServiceCategory::create(['name' => 'Тест']);
    $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);
    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Фотосессия',
        'price' => 1000.00,
        'is_active' => true,
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => 'closed',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'price' => 1000.00,
    ]);

    // Record an order payout transaction
    WalletTransaction::create([
        'wallet_id' => $idol->wallet->id,
        'user_id' => $idol->id,
        'type' => WalletTransactionType::OrderPayout,
        'currency' => 'pink',
        'amount' => 1000.00,
        'balance_before' => 0.00,
        'balance_after' => 880.00,
        'held_balance_before' => 880.00,
        'held_balance_after' => 0.00,
        'status' => WalletTransactionStatus::Completed,
        'reference_type' => Order::class,
        'reference_id' => $order->id,
        'idempotency_key' => 'order_payout_test_1',
        'description' => 'Оплата за заказ #'.$order->id,
        'metadata' => [
            'order_id' => $order->id,
            'gross_amount' => 1000.00,
            'fee_amount' => 120.00,
            'net_payout' => 880.00,
        ],
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.payouts.breakdown', $idol->id));

    $response->assertOk();
    $data = $response->json();

    expect($data['idol']['name'])->toBe($idol->name);
    expect((float) $data['totals']['orders'])->toEqual(880.00);
    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['order']['service_title'])->toBe('Фотосессия');
});
