<?php

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\ChargebackClaim;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AgentReportService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config()->set('services.mandarin.is_sandbox', true);
    config()->set('services.mandarin.webhook_secret', 'test-secret-key-12345');
});

test('mandarin webhook rejects invalid signature or expired timestamp', function () {
    $response = $this->postJson('/api/webhooks/mandarin', [
        'event_id' => 'evt_test_1',
        'event' => 'invoice.paid',
    ], [
        'X-Mandarin-Signature' => 'invalid-sig',
        'X-Mandarin-Timestamp' => (string) now()->timestamp,
    ]);

    $response->assertStatus(400);

    // Expired timestamp (> 300s)
    $responseExpired = $this->postJson('/api/webhooks/mandarin', [
        'event_id' => 'evt_test_2',
        'event' => 'invoice.paid',
    ], [
        'X-Mandarin-Signature' => 'test-signature',
        'X-Mandarin-Timestamp' => (string) now()->subMinutes(10)->timestamp,
    ]);

    $responseExpired->assertStatus(400);
});

test('mandarin webhook processes invoice.paid and credits customer blue coins', function () {
    $customer = User::factory()->create(['is_idol' => false]);
    $wallet = Wallet::firstOrCreate(['user_id' => $customer->id], ['currency' => 'RUB']);
    $wallet->balance = 50.00;
    $wallet->save();

    $payload = [
        'event_id' => 'evt_invoice_topup_1',
        'event' => 'invoice.paid',
        'data' => [
            'customer_id' => $customer->id,
            'amount' => 500.00,
            'operation_id' => 'op_mandarin_123',
            'payment_method' => 'sbp',
        ],
    ];

    $response = $this->postJson('/api/webhooks/mandarin', $payload, [
        'X-Mandarin-Signature' => 'test-signature',
        'X-Mandarin-Timestamp' => (string) now()->timestamp,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'event_id' => 'evt_invoice_topup_1',
            'event_type' => 'invoice.paid',
        ]);

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(550.00);

    $tx = WalletTransaction::where('user_id', $customer->id)
        ->where('type', WalletTransactionType::Deposit)
        ->latest('id')
        ->first();

    expect($tx)->not->toBeNull()
        ->and((float) $tx->amount)->toBe(500.00)
        ->and($tx->status)->toBe(WalletTransactionStatus::Completed)
        ->and($tx->metadata['fiscal_receipt_type'])->toBe('54_fz_advance');
});

test('mandarin webhook enforces idempotency and skips duplicate event_id', function () {
    $customer = User::factory()->create(['is_idol' => false]);
    $wallet = Wallet::firstOrCreate(['user_id' => $customer->id], ['currency' => 'RUB']);
    $wallet->balance = 100.00;
    $wallet->save();

    $payload = [
        'event_id' => 'evt_duplicate_test_1',
        'event' => 'invoice.paid',
        'data' => [
            'customer_id' => $customer->id,
            'amount' => 300.00,
        ],
    ];

    // First delivery
    $r1 = $this->postJson('/api/webhooks/mandarin', $payload, [
        'X-Mandarin-Signature' => 'test-signature',
        'X-Mandarin-Timestamp' => (string) now()->timestamp,
    ]);
    $r1->assertStatus(200)->assertJson(['status' => 'success']);

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(400.00);

    // Second delivery (duplicate event)
    $r2 = $this->postJson('/api/webhooks/mandarin', $payload, [
        'X-Mandarin-Signature' => 'test-signature',
        'X-Mandarin-Timestamp' => (string) now()->timestamp,
    ]);
    $r2->assertStatus(200)->assertJson(['status' => 'already_processed']);

    $wallet->refresh();
    // Balance must NOT be credited a second time!
    expect((float) $wallet->balance)->toBe(400.00);
});

test('mandarin webhook self_employed.bound marks idol tax status verified', function () {
    $idol = User::factory()->create([
        'is_idol' => true,
        'tax_status' => 'none',
        'inn' => '770200112233',
        'payout_phone' => '79001234567',
        'sbp_bank_id' => '100000000004',
        'is_payout_ready' => false,
    ]);

    $payload = [
        'event_id' => 'evt_fns_bound_1',
        'event' => 'self_employed.bound',
        'data' => [
            'inn' => '770200112233',
            'status' => 'bound',
        ],
    ];

    $response = $this->postJson('/api/webhooks/mandarin', $payload, [
        'X-Mandarin-Signature' => 'test-signature',
        'X-Mandarin-Timestamp' => (string) now()->timestamp,
    ]);

    $response->assertStatus(200);

    $idol->refresh();
    expect($idol->tax_status)->toBe('self_employed')
        ->and($idol->tax_verified_at)->not->toBeNull()
        ->and($idol->is_payout_ready)->toBeTrue();
});

test('weekly payouts command dispatches SBP payout for eligible self-employed idols', function () {
    $idol = User::factory()->create([
        'is_idol' => true,
        'tax_status' => 'self_employed',
        'inn' => '770123456789',
        'payout_phone' => '79998887766',
        'sbp_bank_id' => '100000000004',
        'is_payout_ready' => true,
    ]);

    $wallet = Wallet::firstOrCreate(['user_id' => $idol->id], ['currency' => 'RUB']);
    $wallet->pink_balance = 2500.00;
    $wallet->save();

    // Run weekly payout command with --force to test directly
    Artisan::call('orders:schedule-weekly-payouts', ['--force' => true]);

    $wallet->refresh();
    // 2500 was paid out completely
    expect((float) $wallet->pink_balance)->toBe(0.00);

    $tx = WalletTransaction::where('user_id', $idol->id)
        ->where('type', WalletTransactionType::Withdrawal)
        ->latest('id')
        ->first();

    expect($tx)->not->toBeNull()
        ->and((float) $tx->amount)->toBe(-2500.00)
        ->and($tx->metadata['payout_method'])->toBe('sbp')
        ->and((float) $tx->metadata['fee_amount'])->toBe(0.00) // 0% commission!
        ->and((float) $tx->metadata['net_payout'])->toBe(2500.00);
});

test('weekly payouts command respects chargeback claims and threshold', function () {
    $idol = User::factory()->create([
        'is_idol' => true,
        'tax_status' => 'self_employed',
        'payout_phone' => '79998887766',
        'sbp_bank_id' => '100000000004',
        'is_payout_ready' => true,
    ]);

    $wallet = Wallet::firstOrCreate(['user_id' => $idol->id], ['currency' => 'RUB']);
    $wallet->pink_balance = 1200.00; // >= 1000 on surface
    $wallet->save();

    // But idol has open chargeback recourse debt of 400.00!
    ChargebackClaim::create([
        'idol_id' => $idol->id,
        'amount' => 400.00,
        'recovered_amount' => 0.00,
        'status' => 'open',
    ]);

    expect((float) $wallet->available_for_payout)->toBe(800.00); // 1200 - 400 = 800 < 1000

    Artisan::call('orders:schedule-weekly-payouts', ['--force' => true]);

    $wallet->refresh();
    // Balance was NOT deducted because available 800 < min threshold 1000
    expect((float) $wallet->pink_balance)->toBe(1200.00);

    $tx = WalletTransaction::where('user_id', $idol->id)
        ->where('type', WalletTransactionType::Withdrawal)
        ->first();

    expect($tx)->toBeNull();
});

test('monthly agent reports command generates Art. 1008 Civil Code report for idol', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $customer = User::factory()->create(['is_idol' => false]);

    $category = ServiceCategory::create(['name' => ['ru' => 'Аниме', 'en' => 'Anime']]);
    $timeUnit = \App\Models\ServiceTimeUnit::first() ?? \App\Models\ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour']]);
    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Консультация',
        'price' => 2000.00,
        'status' => 'approved',
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Closed,
        'customer_service_fee' => 100.00,
        'customer_service_fee_percent' => 5.0,
        'payout_released_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'service_id' => $service->id,
        'price' => 2000.00,
        'quantity' => 1,
    ]);

    $service = app(AgentReportService::class);
    $report = $service->generateMonthlyReport($idol, now());

    expect($report)->not->toBeNull()
        ->and($report->orders_count)->toBe(1)
        ->and((float) $report->gross_sales)->toBe(2000.00)
        ->and((float) $report->platform_fee_amount)->toBe(240.00) // 12% of 2000
        ->and((float) $report->customer_service_fee_amount)->toBe(100.00) // 5% of 2000
        ->and((float) $report->net_payout_amount)->toBe(1760.00) // 88% of 2000
        ->and($report->status)->toBe('pending_acceptance')
        ->and($report->acceptance_deadline)->not->toBeNull();
});

test('payouts reconcile command updates PendingBank transactions', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $wallet = Wallet::firstOrCreate(['user_id' => $idol->id], ['currency' => 'RUB']);
    $wallet->pink_balance = 0.00;
    $wallet->pink_held_balance = 1500.00;
    $wallet->save();

    $tx = WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $idol->id,
        'type' => WalletTransactionType::Withdrawal,
        'amount' => -1500.00,
        'balance_before' => 1500.00,
        'balance_after' => 0.00,
        'held_balance_before' => 0.00,
        'held_balance_after' => 1500.00,
        'status' => WalletTransactionStatus::PendingBank,
        'currency' => 'pink',
        'idempotency_key' => 'tx_reconcile_test_1',
        'metadata' => [
            'gateway_operation_id' => 'op_stub_reconcile_1',
            'payout_status' => 'pending_bank',
        ],
    ]);
    $tx->created_at = now()->subMinutes(10);
    $tx->save();

    Artisan::call('payouts:reconcile', ['--force' => true]);

    $tx->refresh();
    $wallet->refresh();

    // Stub gateway returns success for any op_stub_*
    expect($tx->status)->toBe(WalletTransactionStatus::Completed)
        ->and((float) $wallet->pink_held_balance)->toBe(0.00);
});

test('self employed monitor receipts command audits closed orders', function () {
    $idol = User::factory()->create([
        'is_idol' => true,
        'inn' => '770123456789',
        'tax_status' => 'self_employed',
    ]);

    $customer = User::factory()->create(['is_idol' => false]);

    Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Closed,
        'payout_released_at' => now()->subDays(2),
    ]);

    $exitCode = Artisan::call('self-employed:monitor-receipts');
    expect($exitCode)->toBe(0);
});
