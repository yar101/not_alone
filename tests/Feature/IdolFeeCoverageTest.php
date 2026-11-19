<?php

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\ContentPack;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DonationService;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.payments.mock_purchases', false);
    PlatformSetting::set('platform_fee_percent', '10.0');
    PlatformSetting::set('withdrawal_fee_percent', '4.0');
    PlatformSetting::set('order_customer_service_fee_percent', '5.0');
});

it('correctly calculates idol fee coverage matrix using option A reverse formula', function () {
    $walletService = app(WalletService::class);

    // Case 0: No coverage (1000 nominal)
    $case0 = $walletService->calculateFeeCoverage(1000.00, false, false);
    expect($case0['amount_charged'])->toBe(1000.00);
    expect($case0['platform_fee'])->toBe(100.00);
    expect($case0['net_payout'])->toBe(900.00);
    expect($case0['expected_withdrawal_payout'])->toBe(864.00);

    // Case 1: Cover platform fee only (10%)
    $case1 = $walletService->calculateFeeCoverage(1000.00, true, false);
    expect($case1['amount_charged'])->toBe(1111.11);
    expect($case1['platform_fee'])->toBe(111.11);
    expect($case1['net_payout'])->toBe(1000.00);
    expect($case1['expected_withdrawal_payout'])->toBe(960.00);

    // Case 2: Cover withdrawal fee only (4%)
    $case2 = $walletService->calculateFeeCoverage(1000.00, false, true);
    expect($case2['amount_charged'])->toBe(1041.67);
    expect($case2['platform_fee'])->toBe(104.17);
    expect($case2['net_payout'])->toBe(937.50);
    expect($case2['expected_withdrawal_payout'])->toBe(900.00);

    // Case 3: Cover both platform and withdrawal fee (10% + 4%)
    // Idol receives exactly 1000.00 on card upon withdrawal!
    $case3 = $walletService->calculateFeeCoverage(1000.00, true, true);
    expect($case3['amount_charged'])->toBe(1157.41);
    expect($case3['platform_fee'])->toBe(115.74);
    expect($case3['net_payout'])->toBe(1041.67);
    expect($case3['expected_withdrawal_payout'])->toBe(1000.00);
});

it('sends donation covering both platform and withdrawal fees so idol gets 100% on card', function () {
    $donor = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $donorWallet = $walletService->getOrCreateWallet($donor);
    $donorWallet->forceFill(['balance' => 2000.00])->save();
    $idolWallet = $walletService->getOrCreateWallet($idol);
    $idolWallet->forceFill(['pink_balance' => 0.00])->save();

    $donationService = app(DonationService::class);

    // 100 nominal donation with both fees covered
    // Charged = 100 / (0.90 * 0.96) = 115.74
    // Platform fee = 11.57
    // Net pink payout = 104.17
    $donation = $donationService->send(
        donor: $donor,
        idol: $idol,
        amount: 100.00,
        feeCoveredByDonor: true,
        message: 'Спасибо за стрим!',
        feeCoveredWithdrawal: true
    );

    expect((float) $donation->amount_charged)->toBe(115.74);
    expect((float) $donation->amount)->toBe(104.17);
    expect((float) $donation->platform_fee)->toBe(11.57);
    expect($donation->fee_covered_by_donor)->toBeTrue();
    expect($donation->fee_covered_withdrawal)->toBeTrue();

    // Donor debited 115.74
    expect((float) $donor->wallet->fresh()->balance)->toBe(1884.26);
    // Idol credited 104.17
    expect((float) $idol->wallet->fresh()->pink_balance)->toBe(104.17);

    // When idol withdraws 104.17, with 4% fee they get exactly 100.00
    $withdrawalTx = $walletService->withdraw($idol, 104.17);
    expect((float) $withdrawalTx->metadata['payout_amount'])->toBe(100.00);
});

it('purchases content pack covering platform and withdrawal fees', function () {
    $seller = User::factory()->create(['is_idol' => true]);
    $buyer = User::factory()->create(['is_idol' => false]);

    $walletService = app(WalletService::class);
    $buyerWallet = $walletService->getOrCreateWallet($buyer);
    $buyerWallet->forceFill(['balance' => 2000.00])->save();
    $sellerWallet = $walletService->getOrCreateWallet($seller);
    $sellerWallet->forceFill(['pink_balance' => 0.00])->save();

    $pack = ContentPack::create([
        'user_id' => $seller->id,
        'title' => 'Эксклюзивный сет',
        'price' => 500.00,
        'status' => 'published',
    ]);

    // 500 nominal with both fees covered:
    // Charged = 500 / (0.90 * 0.96) = 578.70
    // Fee = 57.87
    // Net payout = 520.83
    $purchase = $walletService->purchaseContentPack(
        buyer: $buyer,
        pack: $pack,
        feeCoveredPlatform: true,
        feeCoveredWithdrawal: true
    );

    expect($purchase)->not->toBeNull();
    expect((float) $purchase->amount_charged)->toBe(578.70);
    expect($purchase->fee_covered_platform)->toBeTrue();
    expect($purchase->fee_covered_withdrawal)->toBeTrue();

    // Buyer debited 578.70
    expect((float) $buyer->wallet->fresh()->balance)->toBe(1421.30);
    // Seller pink credited 520.83
    expect((float) $seller->wallet->fresh()->pink_balance)->toBe(520.83);

    // When seller withdraws 520.83 with 4% fee, they get exactly 500.00
    $withdrawalTx = $walletService->withdraw($seller, 520.83);
    expect((float) $withdrawalTx->metadata['payout_amount'])->toBe(500.00);
});

it('pays order with fee coverage and holds combined amount in escrow', function () {
    $customer = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $customerWallet = $walletService->getOrCreateWallet($customer);
    $customerWallet->forceFill(['balance' => 3000.00])->save();
    $idolWallet = $walletService->getOrCreateWallet($idol);
    $idolWallet->forceFill(['pink_balance' => 0.00])->save();

    $category = ServiceCategory::create(['name' => 'Разговоры']);
    $unit = ServiceTimeUnit::create(['name' => '30 мин']);
    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $unit->id,
        'name' => 'Аудиочат',
        'price' => 1000.00,
        'status' => 'approved',
        'is_active' => true,
    ]);

    $orderService = app(OrderService::class);
    [$order] = $orderService->createOrder($customer, $idol, [['id' => $service->id, 'quantity' => 1]]);

    $orderService->accept($order, $idol);

    // Service price = 1000.00
    // Customer fee = 5% = 50.00
    // Fee coverage (both): Amount charged for services = 1157.41, covered = 157.41
    // Total required = 1157.41 + 50.00 = 1207.41
    $orderService->pay(
        $order,
        $customer,
        feeCoveredPlatform: true,
        feeCoveredWithdrawal: true
    );

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid);
    expect($order->fee_covered_platform)->toBeTrue();
    expect($order->fee_covered_withdrawal)->toBeTrue();
    expect((float) $order->idol_fee_covered)->toBe(157.41);

    // Customer balance: 3000 - 1207.41 = 1792.59 available
    $customerWallet = $customer->wallet->fresh();
    expect((float) $customerWallet->balance)->toBe(1792.59);
    // Escrow held balance = 1157.41 (services 1000 + coverage 157.41)
    expect((float) $customerWallet->held_balance)->toBe(1157.41);

    // Verify OrderHold transaction amount is -1157.41
    $holdTx = WalletTransaction::where('reference_type', Order::class)
        ->where('reference_id', $order->id)
        ->where('type', WalletTransactionType::OrderHold)
        ->first();
    expect((float) $holdTx->amount)->toBe(-1157.41);

    // Customer service fee was charged separately (-50.00)
    $feeTx = WalletTransaction::where('reference_type', Order::class)
        ->where('reference_id', $order->id)
        ->where('type', WalletTransactionType::CustomerServiceFee)
        ->first();
    expect((float) $feeTx->amount)->toBe(-50.00);
});

it('refunds 100% of services and covered idol fee to customer when order is cancelled', function () {
    $customer = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $customerWallet = $walletService->getOrCreateWallet($customer);
    $customerWallet->forceFill(['balance' => 3000.00])->save();
    $idolWallet = $walletService->getOrCreateWallet($idol);
    $idolWallet->forceFill(['pink_balance' => 0.00])->save();

    $category = ServiceCategory::create(['name' => 'Разговоры']);
    $unit = ServiceTimeUnit::create(['name' => '30 мин']);
    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $unit->id,
        'name' => 'Аудиочат',
        'price' => 1000.00,
        'status' => 'approved',
        'is_active' => true,
    ]);

    $orderService = app(OrderService::class);
    [$order] = $orderService->createOrder($customer, $idol, [['id' => $service->id, 'quantity' => 1]]);

    $orderService->accept($order, $idol);
    $orderService->pay($order, $customer, feeCoveredPlatform: true, feeCoveredWithdrawal: true);

    // Idol cancels paid order
    $orderService->cancel($order, $idol, 'Не могу выполнить сейчас');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled);

    $customerWallet = $customer->wallet->fresh();
    // Held balance released to 0
    expect((float) $customerWallet->held_balance)->toBe(0.00);
    // Customer receives full refund of held escrow: 1792.59 + 1157.41 = 2950.00
    // Only customer service fee (50.00) remained non-refundable!
    expect((float) $customerWallet->balance)->toBe(2950.00);
});

it('releases payout to idol pink_held_balance with fee coverage on order completion', function () {
    $customer = User::factory()->create(['is_idol' => false]);
    $idol = User::factory()->create(['is_idol' => true]);

    $walletService = app(WalletService::class);
    $customerWallet = $walletService->getOrCreateWallet($customer);
    $customerWallet->forceFill(['balance' => 3000.00])->save();
    $idolWallet = $walletService->getOrCreateWallet($idol);
    $idolWallet->forceFill(['pink_balance' => 0.00, 'pink_held_balance' => 0.00])->save();

    $category = ServiceCategory::create(['name' => 'Разговоры']);
    $unit = ServiceTimeUnit::create(['name' => '30 мин']);
    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $unit->id,
        'name' => 'Аудиочат',
        'price' => 1000.00,
        'status' => 'approved',
        'is_active' => true,
    ]);

    $orderService = app(OrderService::class);
    [$order] = $orderService->createOrder($customer, $idol, [['id' => $service->id, 'quantity' => 1]]);

    $orderService->accept($order, $idol);
    $orderService->pay($order, $customer, feeCoveredPlatform: true, feeCoveredWithdrawal: true);

    // Customer confirms completion
    $orderService->confirmCompletion($order, $customer);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed);

    // Customer held balance released
    $customerWallet = $customer->wallet->fresh();
    expect((float) $customerWallet->held_balance)->toBe(0.00);

    // Idol pink_held_balance credited with 1041.67 (1157.41 - 10% fee 115.74)
    $idolWallet = $idol->wallet->fresh();
    expect((float) $idolWallet->pink_held_balance)->toBe(1041.67);
});
