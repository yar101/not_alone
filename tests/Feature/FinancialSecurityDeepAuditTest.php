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
use App\Models\WalletTransaction;
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
        $this->assertEquals(430, $refundTx->amount);

        $customerWallet = $this->walletService->getOrCreateWallet($customer);
        $this->assertEquals(930, $customerWallet->balance);
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

        // Attempting to buy own pack is rejected with 422
        $resOwn = $this->actingAs($buyer)->postJson('/content-packs/purchase', [
            'items' => [$ownPack->id],
        ]);
        $resOwn->assertStatus(422);
        $resOwn->assertJson(['error' => 'Нельзя приобрести собственный контент-пак.']);
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

        // Check customer wallet was refunded (net refund: 300 - 14% fees = 258 -> 700 + 258 = 958)
        $custWallet = $this->walletService->getOrCreateWallet($customer);
        $this->assertEquals(958, $custWallet->balance);
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

    public function test_customer_cannot_cancel_paid_order_after_idol_confirmed_completion(): void
    {
        $category = ServiceCategory::create(['name' => 'Услуга']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $idol = User::factory()->create(['is_idol' => true]);
        $customer = User::factory()->create();

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Консультация',
            'price' => 500,
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
            'price' => 500,
            'quantity' => 1,
        ]);

        $this->orderService->pay($order, $customer);
        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);

        // Idol confirms completion
        $this->orderService->confirmCompletion($order, $idol);
        $order->refresh();
        $this->assertTrue((bool) $order->completion_confirmed_by_idol);
        $this->assertEquals(OrderStatus::Paid, $order->status);

        // Customer attempts to cancel via HTTP -> 403 Forbidden by Policy
        $response = $this->actingAs($customer)->patchJson("/orders/{$order->id}/cancel", [
            'cancel_reason' => 'Customer trying to bypass',
        ]);
        $response->assertStatus(403);

        // Direct service call throws DomainException
        try {
            $this->orderService->cancel($order, $customer, 'Direct cancel bypass');
            $this->fail('Expected DomainException was not thrown');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Отмена оплаченного заказа клиентом не разрешена', $e->getMessage());
        }

        // Order remains Paid
        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
    }

    public function test_banned_customer_cannot_create_order(): void
    {
        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $idol = User::factory()->create(['is_idol' => true]);
        $customer = User::factory()->create(['is_banned' => true, 'banned_at' => now()]);

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Услуга',
            'price' => 200,
            'is_active' => true,
            'status' => 'approved',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Операция недоступна для заблокированного аккаунта.');
        $this->orderService->createOrder($customer, $idol, [['id' => $service->id, 'quantity' => 1]]);
    }

    public function test_banned_idol_cannot_accept_order(): void
    {
        $idol = User::factory()->create(['is_idol' => true, 'is_banned' => true, 'banned_at' => now()]);
        $customer = User::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Pending,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Операция недоступна для заблокированного аккаунта.');
        $this->orderService->accept($order, $idol);
    }

    public function test_banned_user_cannot_pay_for_order(): void
    {
        $idol = User::factory()->create(['is_idol' => true]);
        $customer = User::factory()->create();

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Accepted,
        ]);

        // Customer banned
        $customer->update(['is_banned' => true, 'banned_at' => now()]);
        try {
            $this->orderService->pay($order, $customer);
            $this->fail('Expected DomainException for banned customer was not thrown');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Операция недоступна для заблокированного аккаунта', $e->getMessage());
        }

        // Unban customer, ban idol
        $customer->update(['is_banned' => false, 'banned_at' => null]);
        $idol->update(['is_banned' => true, 'banned_at' => now()]);
        try {
            $this->orderService->pay($order, $customer);
            $this->fail('Expected DomainException for banned idol was not thrown');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Пользователь недоступен', $e->getMessage());
        }
    }

    public function test_banned_idol_cannot_offer_services_in_conversation(): void
    {
        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);

        $idol = User::factory()->create(['is_idol' => true, 'is_banned' => true, 'banned_at' => now()]);
        $customer = User::factory()->create();

        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Чат',
            'price' => 100,
            'is_active' => true,
            'status' => 'approved',
        ]);

        $conversation = \App\Models\Conversation::create();
        $conversation->participants()->createMany([
            ['user_id' => $idol->id],
            ['user_id' => $customer->id],
        ]);

        $response = $this->actingAs($idol)->postJson("/conversations/{$conversation->id}/offer-services", [
            'services' => [$service->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_platform_fee_transaction_in_pack_purchase_has_idempotency_key(): void
    {
        $seller = User::factory()->create(['is_idol' => true]);
        $buyer = User::factory()->create();
        $this->walletService->deposit($buyer, 1000, 'Init balance', null, [], false);

        $pack = ContentPack::create([
            'user_id' => $seller->id,
            'title' => 'Fee Test Pack',
            'description' => 'Test Desc',
            'price' => 100,
            'status' => 'published',
        ]);

        $this->walletService->purchaseContentPack($buyer, $pack, 10.0);

        $feeTx = WalletTransaction::where('wallet_id', $seller->wallet->id)
            ->where('type', WalletTransactionType::PlatformFee)
            ->where('reference_type', ContentPack::class)
            ->where('reference_id', $pack->id)
            ->first();

        $this->assertNotNull($feeTx);
        $this->assertEquals("pack_fee_{$buyer->id}_{$pack->id}", $feeTx->idempotency_key);
    }

    public function test_admin_cancelling_disputed_order_marks_dispute_as_approved(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Test',
            'email' => 'admin_disp_test@example.com',
            'password' => bcrypt('password'),
        ]);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);
        $this->walletService->deposit($customer, 1000, 'Init balance', null, [], false);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Accepted,
        ]);
        $category = ServiceCategory::create(['name' => 'Категория']);
        $timeUnit = ServiceTimeUnit::create(['name' => 'Час']);
        $service = Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => 'Услуга',
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

        $order->update(['status' => OrderStatus::Disputed]);

        // Admin cancels the order
        $this->orderService->adminTransition($order, OrderStatus::Cancelled, $admin->id, 'Cancelled and refunded');

        $dispute->refresh();
        $this->assertEquals('approved', $dispute->status);

        $order->refresh();
        $this->assertEquals(OrderStatus::Cancelled, $order->status);

        // Customer was refunded (net refund: 300 - 14% fees = 258 -> 700 + 258 = 958)
        $customerWallet = $this->walletService->getOrCreateWallet($customer);
        $this->assertEquals(958, $customerWallet->balance);
        $this->assertEquals(0, $customerWallet->held_balance);
    }

    public function test_user_cannot_reuse_another_users_idempotency_key_at_service_layer(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $key = 'shared-idempotency-key-test-123';
        $txA = $this->walletService->deposit($userA, 100, 'User A deposit', $key, [], false);
        $this->assertEquals(100, $this->walletService->getOrCreateWallet($userA)->balance);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ключ идемпотентности уже использован для другой операции.');

        try {
            $this->walletService->deposit($userB, 100, 'User B deposit with stolen key', $key, [], false);
        } finally {
            $this->assertEquals(0, $this->walletService->getOrCreateWallet($userB)->balance);
            $this->assertEquals(100, $this->walletService->getOrCreateWallet($userA)->fresh()->balance);
        }
    }

    public function test_user_cannot_reuse_idempotency_key_with_different_amount_or_type_at_service_layer(): void
    {
        $user = User::factory()->create(['is_idol' => true]);
        $key = 'same-user-idempotency-key-test-456';

        $tx = $this->walletService->deposit($user, 200, 'Initial deposit', $key, [], false);
        $this->assertNotNull($tx);

        // Different amount on deposit throws DomainException
        try {
            $this->walletService->deposit($user, 500, 'Modified deposit', $key, [], false);
            $this->fail('Expected DomainException for mismatched amount was not thrown.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Ключ идемпотентности уже использован с другими параметрами.', $e->getMessage());
        }

        // Different type (withdraw instead of deposit) throws DomainException
        try {
            $this->walletService->withdraw($user, 200, $key, 'Withdrawal using deposit key');
            $this->fail('Expected DomainException for mismatched type was not thrown.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Ключ идемпотентности уже использован с другими параметрами.', $e->getMessage());
        }
    }

    public function test_idempotency_key_isolation_and_conflict_via_wallet_controller(): void
    {
        $userA = User::factory()->create(['is_idol' => true]);
        $userB = User::factory()->create(['is_idol' => true]);
        $key = 'ctrl-idempotency-deposit-789';

        // 1. User A successfully deposits with key
        $resA = $this->actingAs($userA)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/wallet/deposit', ['amount' => 100]);
        $resA->assertOk();
        $this->assertTrue($resA->json('success'));

        // 2. User B tries to use the same key -> 409 Conflict and no transaction returned
        $resB = $this->actingAs($userB)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/wallet/deposit', ['amount' => 100]);
        $resB->assertStatus(409);
        $resB->assertJson(['error' => 'Ключ идемпотентности уже использован для другой операции.']);
        $this->assertEquals(0, $this->walletService->getOrCreateWallet($userB)->balance);

        // 3. User A tries to use the same key with different amount -> 409 Conflict
        $resA2 = $this->actingAs($userA)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/wallet/deposit', ['amount' => 200]);
        $resA2->assertStatus(409);
        $resA2->assertJson(['error' => 'Ключ идемпотентности уже использован с другими параметрами.']);

        // 4. User A repeats with exact same key and amount -> idempotent 200 OK
        $resA3 = $this->actingAs($userA)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/wallet/deposit', ['amount' => 100]);
        $resA3->assertOk();
        $this->assertEquals($resA->json('transaction.id'), $resA3->json('transaction.id'));
    }

    public function test_withdraw_idempotency_key_isolation_and_conflict_via_wallet_controller(): void
    {
        $idolA = User::factory()->create(['is_idol' => true]);
        $idolB = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($idolA, 1000, 'Init idol A', null, [], false);
        $this->walletService->deposit($idolB, 1000, 'Init idol B', null, [], false);

        $key = 'ctrl-idempotency-withdraw-012';

        // 1. Idol A successfully withdraws with key
        $resA = $this->actingAs($idolA)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/wallet/withdraw', ['amount' => 200]);
        $resA->assertOk();
        $txAId = $resA->json('transaction.id');

        // 2. Idol B tries to withdraw with Idol A's key -> 409 Conflict
        $resB = $this->actingAs($idolB)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/wallet/withdraw', ['amount' => 200]);
        $resB->assertStatus(409);
        $resB->assertJson(['error' => 'Ключ идемпотентности уже использован для другой операции.']);
        $this->assertEquals(1000, $this->walletService->getOrCreateWallet($idolB)->balance);

        // 3. Idol A tries to withdraw with different amount -> 409 Conflict
        $resA2 = $this->actingAs($idolA)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/wallet/withdraw', ['amount' => 300]);
        $resA2->assertStatus(409);
        $resA2->assertJson(['error' => 'Ключ идемпотентности уже использован с другими параметрами.']);

        // 4. Idol A repeats withdraw with same key and amount -> 200 OK, returns same transaction
        $resA3 = $this->actingAs($idolA)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/wallet/withdraw', ['amount' => 200]);
        $resA3->assertOk();
        $this->assertEquals($txAId, $resA3->json('transaction.id'));
        $this->assertEquals(800, $this->walletService->getOrCreateWallet($idolA)->balance);
    }

    public function test_admin_adjust_is_idempotent_with_idempotency_key(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Idempotent Test',
            'email' => 'admin_idempotent_test@example.com',
            'password' => bcrypt('secret'),
        ]);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $key = 'admin-adjust-idempotency-key-999';

        // 1. First admin adjustment creates transaction and credits balance
        $tx1 = $this->walletService->adminAdjust(
            user: $userA,
            amount: 500,
            direction: 'credit',
            description: 'Idempotent bonus',
            adminId: $admin->id,
            type: 'admin_adjustment',
            idempotencyKey: $key
        );

        $this->assertEquals(500, $this->walletService->getOrCreateWallet($userA)->balance);
        $this->assertEquals($key, $tx1->idempotency_key);

        // 2. Second call with exact same parameters returns the same transaction and does NOT credit twice
        $tx2 = $this->walletService->adminAdjust(
            user: $userA,
            amount: 500,
            direction: 'credit',
            description: 'Idempotent bonus duplicate call',
            adminId: $admin->id,
            type: 'admin_adjustment',
            idempotencyKey: $key
        );

        $this->assertEquals($tx1->id, $tx2->id);
        $this->assertEquals(500, $this->walletService->getOrCreateWallet($userA)->fresh()->balance);
        $this->assertEquals(1, WalletTransaction::where('wallet_id', $userA->wallet->id)->count());

        // 3. Different parameters (e.g. amount or direction) throws DomainException
        try {
            $this->walletService->adminAdjust(
                user: $userA,
                amount: 600,
                direction: 'credit',
                description: 'Different amount attempt',
                adminId: $admin->id,
                type: 'admin_adjustment',
                idempotencyKey: $key
            );
            $this->fail('Expected DomainException for mismatched amount was not thrown.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Ключ идемпотентности уже использован с другими параметрами.', $e->getMessage());
        }

        // 4. Using the same key on a different user throws DomainException
        try {
            $this->walletService->adminAdjust(
                user: $userB,
                amount: 500,
                direction: 'credit',
                description: 'Different user attempt',
                adminId: $admin->id,
                type: 'admin_adjustment',
                idempotencyKey: $key
            );
            $this->fail('Expected DomainException for different user was not thrown.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Ключ идемпотентности уже использован для другой операции.', $e->getMessage());
        }

        // 5. Calling admin.transactions.store with idempotency_key is idempotent
        $storeKey = 'admin-store-key-111';
        $postData = [
            'user_id' => $userA->id,
            'direction' => 'credit',
            'type' => 'admin_adjustment',
            'amount' => 150.00,
            'description' => 'Controller idempotent adjustment',
            'idempotency_key' => $storeKey,
        ];

        $res1 = $this->actingAs($admin, 'admin')->post(route('admin.transactions.store'), $postData);
        $res1->assertSessionHasNoErrors();
        $this->assertEquals(650.00, $this->walletService->getOrCreateWallet($userA)->fresh()->balance);

        $res2 = $this->actingAs($admin, 'admin')->post(route('admin.transactions.store'), $postData);
        $res2->assertSessionHasNoErrors();
        $this->assertEquals(650.00, $this->walletService->getOrCreateWallet($userA)->fresh()->balance);
    }
}
