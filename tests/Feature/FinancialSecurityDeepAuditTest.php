<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialSecurityDeepAuditTest extends TestCase
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

    public function test_banned_user_cannot_hold_funds(): void
    {
        $user = User::factory()->create();
        $this->walletService->deposit($user, 1000, 'Init balance', null, [], false);

        $user->update([
            'is_banned' => true,
            'banned_until' => now()->addDays(7),
        ]);

        $order = Order::factory()->create(['customer_id' => $user->id]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Операция недоступна для заблокированного аккаунта.');

        $this->walletService->hold($user, 500, $order, 'Hold for order');
    }

    public function test_banned_buyer_cannot_purchase_content_pack(): void
    {
        $seller = User::factory()->create(['is_banned' => false]);
        $buyer = User::factory()->create(['is_banned' => false]);
        $this->walletService->deposit($buyer, 1000, 'Init balance', null, [], false);

        $buyer->update([
            'is_banned' => true,
            'banned_until' => now()->addDays(7),
        ]);

        $pack = ContentPack::create([
            'user_id' => $seller->id,
            'title' => 'Test Pack',
            'description' => 'Test Desc',
            'price' => 100,
            'status' => 'published',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Операция недоступна для заблокированного аккаунта.');
        $this->walletService->purchaseContentPack($buyer, $pack);
    }

    public function test_cannot_purchase_pack_if_author_is_banned(): void
    {
        $seller = User::factory()->create([
            'is_banned' => true,
            'banned_until' => now()->addDays(7),
        ]);
        $buyer = User::factory()->create(['is_banned' => false]);
        $this->walletService->deposit($buyer, 1000, 'Init balance', null, [], false);

        $pack = ContentPack::create([
            'user_id' => $seller->id,
            'title' => 'Test Pack',
            'description' => 'Test Desc',
            'price' => 100,
            'status' => 'published',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Автор контент-пака заблокирован.');
        $this->walletService->purchaseContentPack($buyer, $pack);
    }

    public function test_cannot_purchase_unpublished_or_own_pack(): void
    {
        $user = User::factory()->create();
        $this->walletService->deposit($user, 1000, 'Init balance', null, [], false);

        $ownPack = ContentPack::create([
            'user_id' => $user->id,
            'title' => 'Own Pack',
            'description' => 'My Pack',
            'price' => 100,
            'status' => 'published',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Нельзя покупать собственный контент-пак');
        $this->walletService->purchaseContentPack($user, $ownPack);
    }

    public function test_purchase_content_pack_validates_platform_fee_percent(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $this->walletService->deposit($buyer, 1000, 'Init balance', null, [], false);

        $pack = ContentPack::create([
            'user_id' => $seller->id,
            'title' => 'Valid Pack',
            'description' => 'Pack desc',
            'price' => 100,
            'status' => 'published',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Процент комиссии платформы должен быть в диапазоне от 0 до 100.');
        $this->walletService->purchaseContentPack($buyer, $pack, 150.0);
    }

    public function test_deactivated_idol_wallet_does_not_block_customer_refund(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($customer, 1000, 'Customer deposit', null, [], false);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        // Customer puts hold on order
        $this->walletService->hold($customer, 500, $order, 'Order hold');

        // Scam idol gets deactivated
        $idolWallet = $this->walletService->getOrCreateWallet($idol);
        $idolWallet->update(['is_active' => false]);

        // Customer gets refunded without exception
        $refundTx = $this->walletService->refundHold($order, 'Idol was banned for fraud');

        $this->assertNotNull($refundTx);
        $this->assertEquals(WalletTransactionType::OrderRefund, $refundTx->type);
        $this->assertEquals(500, $refundTx->amount);

        $customerWallet = $this->walletService->getOrCreateWallet($customer);
        $this->assertEquals(1000, $customerWallet->balance);
        $this->assertEquals(0, $customerWallet->held_balance);
    }

    public function test_user_account_deletion_deactivates_wallet(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $wallet = $this->walletService->getOrCreateWallet($user);
        $this->assertTrue($wallet->is_active);

        $response = $this->actingAs($user)->delete('/settings', [
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/');
        $wallet->refresh();
        $this->assertFalse($wallet->is_active);
    }

    public function test_idempotency_key_longer_than_64_characters_is_rejected_by_controller(): void
    {
        $user = User::factory()->create(['is_idol' => true]);
        $this->walletService->getOrCreateWallet($user);
        $this->walletService->deposit($user, 500, 'Seed balance', null, [], false);

        $longKey = str_repeat('a', 65);

        // Deposit endpoint validation via body
        $response = $this->actingAs($user)->postJson('/api/wallet/deposit', [
            'amount' => 100,
            'idempotency_key' => $longKey,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('idempotency_key');

        // Header validation
        $response2 = $this->actingAs($user)
            ->withHeader('X-Idempotency-Key', $longKey)
            ->postJson('/api/wallet/deposit', [
                'amount' => 100,
            ]);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors('idempotency_key');

        // Withdraw endpoint validation via body
        $response3 = $this->actingAs($user)->postJson('/api/wallet/withdraw', [
            'amount' => 100,
            'idempotency_key' => $longKey,
        ]);
        $response3->assertStatus(422);
        $response3->assertJsonValidationErrors('idempotency_key');
    }

    public function test_admin_transaction_rejects_debit_on_deposit_and_credit_on_withdrawal(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Test',
            'email' => 'admin_test_security@example.com',
            'password' => 'secret',
        ]);
        $user = User::factory()->create();
        $this->walletService->deposit($user, 500, 'Initial balance', null, [], false);

        // 1. Service layer rejection
        try {
            $this->walletService->adminAdjust(
                user: $user,
                amount: 100,
                direction: 'debit',
                description: 'Invalid debit as deposit',
                adminId: $admin->id,
                type: 'deposit'
            );
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Списание не может иметь тип «deposit»', $e->getMessage());
        }

        try {
            $this->walletService->adminAdjust(
                user: $user,
                amount: 100,
                direction: 'credit',
                description: 'Invalid credit as withdrawal',
                adminId: $admin->id,
                type: 'withdrawal'
            );
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Начисление не может иметь тип «withdrawal»', $e->getMessage());
        }

        // 2. Controller layer validation
        $this->actingAs($admin, 'admin');

        $response1 = $this->post('/admin/transactions', [
            'user_id' => $user->id,
            'direction' => 'debit',
            'type' => 'deposit',
            'amount' => 50,
            'description' => 'Test admin debit',
        ]);
        $response1->assertSessionHasErrors('type');

        $response2 = $this->post('/admin/transactions', [
            'user_id' => $user->id,
            'direction' => 'credit',
            'type' => 'withdrawal',
            'amount' => 50,
            'description' => 'Test admin credit',
        ]);
        $response2->assertSessionHasErrors('type');
    }

    public function test_content_pack_purchase_controller_validations(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $this->walletService->deposit($buyer, 500, 'Init balance', null, [], false);

        $pack1 = ContentPack::create([
            'user_id' => $seller->id,
            'title' => 'Pack 1',
            'description' => 'Desc 1',
            'price' => 50,
            'status' => 'published',
        ]);

        $ownPack = ContentPack::create([
            'user_id' => $buyer->id,
            'title' => 'Own Pack',
            'description' => 'Desc 2',
            'price' => 50,
            'status' => 'published',
        ]);

        // Attempting to buy own pack skips purchase and returns 0 purchased items
        $resOwn = $this->actingAs($buyer)->postJson('/content-packs/purchase', [
            'items' => [$ownPack->id],
        ]);
        $resOwn->assertStatus(200);
        $resOwn->assertJson(['success' => true, 'purchased' => []]);
        $this->assertEquals(0, ContentPackPurchase::where('user_id', $buyer->id)->where('content_pack_id', $ownPack->id)->count());

        // Attempting to buy non-existent or unpublished pack alongside valid pack
        $resMissing = $this->actingAs($buyer)->postJson('/content-packs/purchase', [
            'items' => [$pack1->id, 999999],
        ]);
        $resMissing->assertStatus(422);
        $resMissing->assertJson(['error' => 'Один или несколько контент-паков недоступны для покупки.']);

        // Successful purchase
        $resOk = $this->actingAs($buyer)->postJson('/content-packs/purchase', [
            'items' => [$pack1->id],
        ]);
        $resOk->assertStatus(200);
        $resOk->assertJson(['success' => true, 'purchased' => [$pack1->id]]);
    }

    public function test_dispute_resolve_is_atomic(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Dispute',
            'email' => 'admin_dispute@example.com',
            'password' => 'secret',
        ]);
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($customer, 1000, 'Customer deposit', null, [], false);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Disputed,
            'paid_at' => now(),
        ]);

        $category = ServiceCategory::create(['name' => 'Тест']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Чат',
            'price' => 300,
            'is_active' => true,
            'status' => 'approved',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'price' => 300,
            'quantity' => 1,
        ]);

        $this->walletService->hold($customer, 300, $order, 'Order hold');

        $dispute = OrderDispute::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'status' => 'open',
            'reason' => 'Service not delivered',
            'details' => 'Idol never showed up',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->patch("/admin/disputes/{$dispute->id}/resolve", [
            'decision' => 'approved',
            'admin_note' => 'Dispute approved, refunding customer',
        ]);

        $response->assertRedirect();
        $dispute->refresh();
        $this->assertEquals('approved', $dispute->status);
        $this->assertEquals('Dispute approved, refunding customer', $dispute->admin_note);

        $order->refresh();
        $this->assertEquals(OrderStatus::Refunded, $order->status);

        // Check customer wallet was refunded
        $custWallet = $this->walletService->getOrCreateWallet($customer);
        $this->assertEquals(1000, $custWallet->balance);
        $this->assertEquals(0, $custWallet->held_balance);
    }

    public function test_cannot_add_item_to_order_if_customer_or_idol_banned(): void
    {
        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $idol = User::factory()->create();
        $customer = User::factory()->create();

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Test Service',
            'price' => 100,
            'is_active' => true,
            'status' => 'approved',
            'is_trial' => false,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Pending,
        ]);

        // 1. Customer banned
        $customer->update(['is_banned' => true, 'banned_at' => now()]);
        try {
            $this->orderService->addItem($order, $service->id, $customer);
            $this->fail('Expected DomainException for banned customer was not thrown');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Операция недоступна для заблокированного аккаунта', $e->getMessage());
        }

        // Unban customer
        $customer->update(['is_banned' => false, 'banned_at' => null]);

        // 2. Idol banned
        $idol->update(['is_banned' => true, 'banned_at' => now()]);
        try {
            $this->orderService->addItem($order, $service->id, $customer);
            $this->fail('Expected DomainException for banned idol was not thrown');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Пользователь недоступен', $e->getMessage());
        }
    }
}
