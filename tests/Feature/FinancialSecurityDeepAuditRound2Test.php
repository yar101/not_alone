<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\ContentPack;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FinancialSecurityDeepAuditRound2Test extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);
        $this->orderService = app(OrderService::class);
    }

    public function test_release_idol_holds_command_resilient_to_deactivated_idol_wallets(): void
    {
        PlatformSetting::set('order_dispute_window_minutes', 60);

        $customer1 = User::factory()->create();
        $customer2 = User::factory()->create();
        $idol1 = User::factory()->create(['is_idol' => true]);
        $idol2 = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($customer1, 1000, 'Init balance', null, [], false);
        $this->walletService->deposit($customer2, 1000, 'Init balance', null, [], false);

        // Order 1 (idol1)
        $order1 = Order::create([
            'customer_id' => $customer1->id,
            'idol_id' => $idol1->id,
            'status' => OrderStatus::Completed,
            'paid_at' => now()->subHours(5),
            'completed_at' => now(),
        ]);
        $this->walletService->hold($customer1, 500, $order1, 'Order 1 hold');
        $this->walletService->releaseHold($order1);

        // Advance completed_at past dispute window and deactivate idol1 wallet
        $order1->update(['completed_at' => now()->subHours(2)]);
        $idol1Wallet = $this->walletService->getOrCreateWallet($idol1);
        $idol1Wallet->update(['is_active' => false]);

        // Order 2 (idol2)
        $order2 = Order::create([
            'customer_id' => $customer2->id,
            'idol_id' => $idol2->id,
            'status' => OrderStatus::Completed,
            'paid_at' => now()->subHours(5),
            'completed_at' => now(),
        ]);
        $this->walletService->hold($customer2, 600, $order2, 'Order 2 hold');
        $this->walletService->releaseHold($order2);

        // Advance completed_at past dispute window for order 2
        $order2->update(['completed_at' => now()->subHours(2)]);

        // Run the command: should not throw DomainException
        $exitCode = Artisan::call('orders:release-idol-holds');
        $this->assertEquals(0, $exitCode);

        // Order 1: payout was safely skipped and remains held
        $order1->refresh();
        $this->assertNull($order1->payout_released_at);

        // Order 2: payout was successfully released to idol2
        $order2->refresh();
        $this->assertNotNull($order2->payout_released_at);
        $idol2Wallet = $this->walletService->getOrCreateWallet($idol2);
        $this->assertGreaterThan(0, (float) $idol2Wallet->balance);
    }

    public function test_auto_complete_orders_command_completes_overdue_paid_orders(): void
    {
        PlatformSetting::set('order_auto_complete_delay', 72);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);
        $this->walletService->deposit($customer, 1000, 'Init balance', null, [], false);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now()->subHours(80),
        ]);

        $this->walletService->hold($customer, 400, $order, 'Order hold');

        $exitCode = Artisan::call('orders:auto-complete');
        $this->assertEquals(0, $exitCode);

        $order->refresh();
        $this->assertEquals(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);

        // Funds are in idol's held_balance
        $idolWallet = $this->walletService->getOrCreateWallet($idol);
        $this->assertGreaterThan(0, (float) $idolWallet->held_balance);
    }

    public function test_create_order_rejects_trial_quantity_greater_than_one(): void
    {
        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $trialService = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Пробная услуга',
            'price' => 500,
            'is_trial' => true,
            'is_active' => true,
            'status' => 'approved',
        ]);

        // Attempting quantity > 1 on a trial service throws DomainException
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ознакомительная услуга может быть заказана только в количестве 1 шт.');

        $this->orderService->createOrder($customer, $idol, [
            ['id' => $trialService->id, 'quantity' => 5],
        ]);
    }

    public function test_create_order_accepts_trial_with_quantity_one(): void
    {
        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $trialService = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Пробная услуга',
            'price' => 500,
            'is_trial' => true,
            'is_active' => true,
            'status' => 'approved',
        ]);

        [$order, $conv] = $this->orderService->createOrder($customer, $idol, [
            ['id' => $trialService->id, 'quantity' => 1],
        ]);

        $this->assertNotNull($order);
        $item = $order->items->first();
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(0, $item->price);
    }

    public function test_content_pack_bulk_purchase_rejects_own_pack(): void
    {
        $author = User::factory()->create(['is_idol' => true]);
        $this->walletService->deposit($author, 1000, 'Init balance', null, [], false);

        $pack = ContentPack::create([
            'user_id' => $author->id,
            'title' => 'My Pack',
            'description' => 'Desc',
            'price' => 200,
            'status' => 'published',
        ]);

        $response = $this->actingAs($author)->postJson('/content-packs/purchase', [
            'items' => [$pack->id],
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Нельзя приобрести собственный контент-пак.']);
    }

    public function test_wallet_controller_supports_plural_withdrawals_filter(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $this->walletService->deposit($idol, 2000, 'Init balance', null, [], false);
        $this->walletService->withdraw($idol, 500, 'wt_key_1', 'Withdrawal test');

        // Test Inertia /wallet?filter=withdrawals
        $responseInertia = $this->actingAs($idol)->get('/wallet?filter=withdrawals');
        $responseInertia->assertStatus(200);

        // Test API /api/wallet?filter=withdrawals
        $responseJson = $this->actingAs($idol)->getJson('/api/wallet?filter=withdrawals');
        $responseJson->assertStatus(200);

        $txs = $responseJson->json('transactions');
        $this->assertNotEmpty($txs);
        foreach ($txs as $tx) {
            $this->assertEquals(WalletTransactionType::Withdrawal->value, $tx['type']);
        }
    }

    public function test_cannot_offer_trial_service_in_existing_order_chat(): void
    {
        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $regularService = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Обычная услуга',
            'price' => 300,
            'is_trial' => false,
            'is_active' => true,
            'status' => 'approved',
        ]);

        $trialService = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Пробная услуга',
            'price' => 300,
            'is_trial' => true,
            'is_active' => true,
            'status' => 'approved',
        ]);

        [$order, $conv] = $this->orderService->createOrder($customer, $idol, [
            ['id' => $regularService->id, 'quantity' => 1],
        ]);

        // Attempting to offer trial service in existing order chat is rejected with 422
        $response = $this->actingAs($idol)->postJson("/conversations/{$conv->id}/offer-services", [
            'services' => [$trialService->id],
        ]);

        $response->assertStatus(422);
    }
}
