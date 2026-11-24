<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\ContentPack;
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

class DualCurrencyWalletTest extends TestCase
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
        PlatformSetting::set('withdrawal_fee_percent', 4.0);
    }

    private function createOrderWithService(User $customer, User $idol, float $price = 1000.0): Order
    {
        $category = ServiceCategory::create(['name' => 'Category_'.uniqid()]);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Hour_'.uniqid()]);

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => "Service {$price}",
            'price' => $price,
            'is_active' => true,
            'status' => 'approved',
        ]);

        [$order] = $this->orderService->createOrder($customer, $idol, [
            ['id' => $service->id, 'quantity' => 1],
        ]);

        $this->orderService->accept($order, $idol);

        return $order;
    }

    public function test_client_deposit_credits_blue_coins(): void
    {
        $client = User::factory()->create(['is_idol' => false]);
        $wallet = $this->walletService->getOrCreateWallet($client);

        $tx = $this->walletService->deposit($client, 1000.0, 'Test Deposit');

        $wallet->refresh();
        $this->assertEquals(960.0, (float) $wallet->balance); // 4% deposit fee
        $this->assertEquals(0.0, (float) $wallet->pink_balance);
        $this->assertEquals('blue', $tx->currency);
    }

    public function test_client_order_payment_holds_blue_coins(): void
    {
        $client = User::factory()->create(['is_idol' => false]);
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($client, 2000.0, 'Initial Deposit', null, [], false); // 2000 net

        $order = $this->createOrderWithService($client, $idol, 1000.0);

        $this->orderService->pay($order, $client);

        $clientWallet = $this->walletService->getOrCreateWallet($client);
        $this->assertEquals(950.0, (float) $clientWallet->balance); // 2000 - 1000 hold - 50 fee (5%)
        $this->assertEquals(1000.0, (float) $clientWallet->held_balance);
        $this->assertEquals(0.0, (float) $clientWallet->pink_balance);
    }

    public function test_completed_order_credits_idol_pink_coins_in_held_balance(): void
    {
        $client = User::factory()->create(['is_idol' => false]);
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($client, 2000.0, 'Deposit', null, [], false);

        $order = $this->createOrderWithService($client, $idol, 1000.0);

        $this->orderService->pay($order, $client);
        $this->walletService->releaseHold($order);

        $idolWallet = $this->walletService->getOrCreateWallet($idol);
        $clientWallet = $this->walletService->getOrCreateWallet($client);

        // Client held balance released
        $this->assertEquals(0.0, (float) $clientWallet->held_balance);

        // Idol receives 900 pink coins (1000 - 10% platform fee) into pink_held_balance
        $this->assertEquals(900.0, (float) $idolWallet->pink_held_balance);
        $this->assertEquals(0.0, (float) $idolWallet->pink_balance);
        $this->assertEquals(0.0, (float) $idolWallet->balance);

        $payoutTx = WalletTransaction::where('wallet_id', $idolWallet->id)
            ->where('type', WalletTransactionType::OrderPayout)
            ->first();
        $this->assertNotNull($payoutTx);
        $this->assertEquals('pink', $payoutTx->currency);
    }

    public function test_releasing_idol_payout_moves_funds_from_pink_held_to_pink_balance(): void
    {
        $client = User::factory()->create(['is_idol' => false]);
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($client, 2000.0, 'Deposit', null, [], false);

        $order = $this->createOrderWithService($client, $idol, 1000.0);

        $this->orderService->pay($order, $client);
        $this->walletService->releaseHold($order);

        $order->update(['status' => OrderStatus::Completed]);
        $order->forceFill(['completed_at' => now()->subMinutes((int) PlatformSetting::get('order_dispute_window_minutes', 60) + 1)])->save();
        $this->walletService->releaseIdolPayout($order);

        $idolWallet = $this->walletService->getOrCreateWallet($idol);
        $this->assertEquals(0.0, (float) $idolWallet->pink_held_balance);
        $this->assertEquals(900.0, (float) $idolWallet->pink_balance);
        $this->assertEquals(0.0, (float) $idolWallet->balance); // Blue balance unaffected
    }

    public function test_content_pack_purchase_pays_blue_credits_pink(): void
    {
        $buyer = User::factory()->create(['is_idol' => false]);
        $authorIdol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($buyer, 1000.0, 'Buyer Deposit', null, [], false);

        $pack = ContentPack::create([
            'user_id' => $authorIdol->id,
            'title' => 'Awesome Pack',
            'description' => 'Pack desc',
            'status' => 'published',
            'price' => 500,
        ]);

        $this->walletService->purchaseContentPack($buyer, $pack);

        $buyerWallet = $this->walletService->getOrCreateWallet($buyer);
        $authorWallet = $this->walletService->getOrCreateWallet($authorIdol);

        // Buyer deducted 500 blue coins
        $this->assertEquals(500.0, (float) $buyerWallet->balance);

        // Author credited 450 pink coins (500 - 10% fee)
        $this->assertEquals(450.0, (float) $authorWallet->pink_balance);
        $this->assertEquals(0.0, (float) $authorWallet->balance);

        $saleTx = WalletTransaction::where('wallet_id', $authorWallet->id)
            ->where('type', WalletTransactionType::PackSale)
            ->first();
        $this->assertNotNull($saleTx);
        $this->assertEquals('pink', $saleTx->currency);
    }

    public function test_idol_can_convert_pink_to_blue_coins_1_to_1(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $wallet = $this->walletService->getOrCreateWallet($idol);

        $wallet->forceFill([
            'pink_balance' => 1000.0,
            'balance' => 0.0,
        ])->save();

        $result = $this->walletService->convertPinkToBlue($idol, 400.0, 'test_conv_1');

        $wallet->refresh();
        $this->assertEquals(600.0, (float) $wallet->pink_balance);
        $this->assertEquals(400.0, (float) $wallet->balance);

        $this->assertEquals('pink', $result['pink_transaction']->currency);
        $this->assertEquals(WalletTransactionType::PinkToBlueConversion, $result['pink_transaction']->type);
        $this->assertEquals(-400.0, (float) $result['pink_transaction']->amount);

        $this->assertEquals('blue', $result['blue_transaction']->currency);
        $this->assertEquals(WalletTransactionType::BlueFromPinkConversion, $result['blue_transaction']->type);
        $this->assertEquals(400.0, (float) $result['blue_transaction']->amount);
    }

    public function test_cannot_convert_more_pink_coins_than_available(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $wallet = $this->walletService->getOrCreateWallet($idol);

        $wallet->forceFill(['pink_balance' => 100.0])->save();

        $this->expectException(InsufficientFundsException::class);
        $this->walletService->convertPinkToBlue($idol, 200.0);
    }

    public function test_non_idol_client_cannot_convert_pink_to_blue(): void
    {
        $client = User::factory()->create(['is_idol' => false]);
        $this->walletService->getOrCreateWallet($client);

        $this->expectException(\DomainException::class);
        $this->walletService->convertPinkToBlue($client, 50.0);
    }

    public function test_withdraw_only_allowed_from_pink_balance_and_only_for_idols(): void
    {
        $client = User::factory()->create(['is_idol' => false]);
        $this->walletService->deposit($client, 1000.0, 'Deposit', null, [], false);

        // Client cannot withdraw
        $this->expectException(\DomainException::class);
        $this->walletService->withdraw($client, 500.0);
    }

    public function test_idol_withdraws_from_pink_balance(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $wallet = $this->walletService->getOrCreateWallet($idol);

        $wallet->forceFill([
            'pink_balance' => 1000.0,
            'balance' => 500.0, // Blue balance
        ])->save();

        $tx = $this->walletService->withdraw($idol, 400.0, 'test_with_1');

        $wallet->refresh();
        $this->assertEquals(600.0, (float) $wallet->pink_balance);
        $this->assertEquals(500.0, (float) $wallet->balance); // Blue balance untouched
        $this->assertEquals('pink', $tx->currency);
    }

    public function test_client_api_does_not_reveal_pink_balances(): void
    {
        $client = User::factory()->create(['is_idol' => false]);
        $wallet = $this->walletService->getOrCreateWallet($client);
        $wallet->forceFill(['balance' => 300.0])->save();

        $response = $this->actingAs($client)->getJson('/api/wallet');
        $response->assertOk();

        $response->assertJsonMissing(['pink_balance']);
        $response->assertJsonMissing(['pink_held_balance']);
        $response->assertJsonMissing(['pink_total_balance']);
        $this->assertEquals(300.0, (float) data_get($response->json(), 'wallet.balance'));
    }

    public function test_idol_api_reveals_both_blue_and_pink_balances(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $wallet = $this->walletService->getOrCreateWallet($idol);
        $wallet->forceFill([
            'balance' => 150.0,
            'pink_balance' => 750.0,
            'pink_held_balance' => 200.0,
        ])->save();

        $response = $this->actingAs($idol)->getJson('/api/wallet');
        $response->assertOk();

        $this->assertEquals(150.0, (float) data_get($response->json(), 'wallet.balance'));
        $this->assertEquals(750.0, (float) data_get($response->json(), 'wallet.pink_balance'));
        $this->assertEquals(200.0, (float) data_get($response->json(), 'wallet.pink_held_balance'));
        $this->assertEquals(950.0, (float) data_get($response->json(), 'wallet.pink_total_balance'));
    }

    public function test_convert_pink_to_blue_endpoint(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $wallet = $this->walletService->getOrCreateWallet($idol);
        $wallet->forceFill([
            'pink_balance' => 500.0,
            'balance' => 100.0,
        ])->save();

        $response = $this->actingAs($idol)->postJson('/api/wallet/convert-pink-to-blue', [
            'amount' => 200.0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(300.0, (float) data_get($response->json(), 'pink_balance'));
        $this->assertEquals(300.0, (float) data_get($response->json(), 'balance'));

        $wallet->refresh();
        $this->assertEquals(300.0, (float) $wallet->pink_balance);
        $this->assertEquals(300.0, (float) $wallet->balance);
    }

    public function test_refund_completed_order_claws_back_pink_coins_from_idol(): void
    {
        $client = User::factory()->create(['is_idol' => false]);
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($client, 2000.0, 'Deposit', null, [], false);

        $order = $this->createOrderWithService($client, $idol, 1000.0);

        $this->orderService->pay($order, $client);
        $this->walletService->releaseHold($order);

        // While in dispute window hold, refund the order (dispute approved by admin)
        $this->walletService->refundHold($order, 'Dispute approved', false);

        $idolWallet = $this->walletService->getOrCreateWallet($idol)->fresh();
        $clientWallet = $this->walletService->getOrCreateWallet($client)->fresh();

        // Idol pink held balance cancelled to 0
        $this->assertEquals(0.0, (float) $idolWallet->pink_held_balance);
        $this->assertEquals(0.0, (float) $idolWallet->pink_balance);

        // Client refunded 100% of service price
        $this->assertEquals(1950.0, (float) $clientWallet->balance);
    }

    public function test_convert_pink_to_blue_idempotency_and_conflict(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $wallet = $this->walletService->getOrCreateWallet($idol);
        $wallet->forceFill([
            'pink_balance' => 1000.0,
            'balance' => 0.0,
        ])->save();

        // 1. Service level idempotent retry
        $res1 = $this->walletService->convertPinkToBlue($idol, 200.0, 'idemp_conv_test');
        $this->assertEquals(800.0, $res1['pink_balance']);
        $this->assertEquals(200.0, $res1['balance']);

        // Retry with same key and same amount -> returns existing
        $res2 = $this->walletService->convertPinkToBlue($idol, 200.0, 'idemp_conv_test');
        $this->assertEquals($res1['pink_transaction']->id, $res2['pink_transaction']->id);
        $this->assertEquals($res1['blue_transaction']->id, $res2['blue_transaction']->id);
        $this->assertEquals(800.0, $res2['pink_balance']);
        $this->assertEquals(200.0, $res2['balance']);

        // Retry with same key and different amount -> throws DomainException
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ключ идемпотентности уже использован с другими параметрами.');
        $this->walletService->convertPinkToBlue($idol, 300.0, 'idemp_conv_test');
    }

    public function test_convert_pink_to_blue_endpoint_idempotency_and_409(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $wallet = $this->walletService->getOrCreateWallet($idol);
        $wallet->forceFill([
            'pink_balance' => 1000.0,
            'balance' => 0.0,
        ])->save();

        $key = 'api_idemp_key_123';

        // 1st request -> 200 OK
        $r1 = $this->actingAs($idol)->postJson('/api/wallet/convert-pink-to-blue', [
            'amount' => 150.0,
            'idempotency_key' => $key,
        ]);
        $r1->assertOk();
        $this->assertEquals(850.0, (float) data_get($r1->json(), 'pink_balance'));
        $this->assertEquals(150.0, (float) data_get($r1->json(), 'balance'));

        // 2nd request (same key, same amount) -> 200 OK without double charging
        $r2 = $this->actingAs($idol)->postJson('/api/wallet/convert-pink-to-blue', [
            'amount' => 150.0,
            'idempotency_key' => $key,
        ]);
        $r2->assertOk();
        $this->assertEquals(850.0, (float) data_get($r2->json(), 'pink_balance'));
        $this->assertEquals(150.0, (float) data_get($r2->json(), 'balance'));

        // 3rd request (same key, different amount) -> 409 Conflict
        $r3 = $this->actingAs($idol)->postJson('/api/wallet/convert-pink-to-blue', [
            'amount' => 250.0,
            'idempotency_key' => $key,
        ]);
        $r3->assertStatus(409);
        $this->assertStringContainsString('идемпотентност', mb_strtolower($r3->json('error')));
    }

    public function test_wallet_to_array_hides_pink_fields_on_client_and_exposes_on_idol(): void
    {
        $client = User::factory()->create(['is_idol' => false]);
        $clientWallet = $this->walletService->getOrCreateWallet($client);
        $clientWallet->forceFill([
            'balance' => 120.0,
            'held_balance' => 30.0,
            'pink_balance' => 50.0,
            'pink_held_balance' => 10.0,
        ])->save();

        $clientArray = $clientWallet->toArray();
        $this->assertArrayHasKey('balance', $clientArray);
        $this->assertArrayHasKey('held_balance', $clientArray);
        $this->assertArrayHasKey('total_balance', $clientArray);
        $this->assertArrayNotHasKey('pink_balance', $clientArray);
        $this->assertArrayNotHasKey('pink_held_balance', $clientArray);
        $this->assertArrayNotHasKey('pink_total_balance', $clientArray);

        $idol = User::factory()->create(['is_idol' => true]);
        $idolWallet = $this->walletService->getOrCreateWallet($idol);
        $idolWallet->forceFill([
            'balance' => 200.0,
            'held_balance' => 50.0,
            'pink_balance' => 600.0,
            'pink_held_balance' => 100.0,
        ])->save();

        $idolArray = $idolWallet->toArray();
        $this->assertArrayHasKey('balance', $idolArray);
        $this->assertArrayHasKey('held_balance', $idolArray);
        $this->assertArrayHasKey('total_balance', $idolArray);
        $this->assertArrayHasKey('pink_balance', $idolArray);
        $this->assertArrayHasKey('pink_held_balance', $idolArray);
        $this->assertArrayHasKey('pink_total_balance', $idolArray);
        $this->assertEquals(700.0, $idolArray['pink_total_balance']);
    }

    public function test_64_character_idempotency_key_does_not_overflow(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $wallet = $this->walletService->getOrCreateWallet($idol);
        $wallet->forceFill([
            'pink_balance' => 500.0,
            'balance' => 0.0,
        ])->save();

        $key64 = str_repeat('k', 64);
        $result = $this->walletService->convertPinkToBlue($idol, 100.0, $key64);

        $this->assertNotNull($result['pink_transaction']->idempotency_key);
        $this->assertLessThanOrEqual(64, strlen($result['pink_transaction']->idempotency_key));
        $this->assertNotNull($result['blue_transaction']->idempotency_key);
        $this->assertLessThanOrEqual(64, strlen($result['blue_transaction']->idempotency_key));
    }
}
