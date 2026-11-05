<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ContentPack;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Task6SecurityAndModelIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $customer;

    private User $idol;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::factory()->create();
        $this->idol = User::factory()->create(['is_idol' => true]);

        $cat = ServiceCategory::create(['name' => 'Категория']);
        $unit = ServiceTimeUnit::create(['name' => 'Час']);

        $this->service = Service::create([
            'user_id' => $this->idol->id,
            'category_id' => $cat->id,
            'time_unit_id' => $unit->id,
            'name' => 'Консультация',
            'price' => 1000,
            'is_active' => true,
            'status' => 'approved',
        ]);
    }

    public function test_unverified_user_cannot_access_orders_or_wallet(): void
    {
        $unverified = User::factory()->unverified()->create();

        $responseOrder = $this->actingAs($unverified)->get(route('orders.index'));
        $responseOrder->assertRedirect(route('verification.notice'));

        $responseWallet = $this->actingAs($unverified)->get(route('wallet.show'));
        $responseWallet->assertRedirect(route('verification.notice'));
    }

    public function test_order_add_item_enforces_maximum_quantity_of_100(): void
    {
        $orderService = app(OrderService::class);
        [$order] = $orderService->createOrder($this->customer, $this->idol, [
            ['id' => $this->service->id, 'quantity' => 1],
        ]);

        $item = $order->items()->first();
        $item->update(['quantity' => 100]);

        // Attempting to add item when quantity is already 100 via service throws DomainException
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Превышено максимальное количество для услуги (100).');
        $orderService->addItem($order, $this->service->id, $this->customer);
    }

    public function test_order_add_item_controller_returns_422_when_exceeding_100(): void
    {
        $orderService = app(OrderService::class);
        [$order] = $orderService->createOrder($this->customer, $this->idol, [
            ['id' => $this->service->id, 'quantity' => 1],
        ]);

        $item = $order->items()->first();
        $item->update(['quantity' => 100]);

        $response = $this->actingAs($this->customer)->postJson(route('orders.items.add', $order), [
            'service_id' => $this->service->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Превышено максимальное количество для услуги (100).']);
    }

    public function test_order_service_create_order_throws_domain_exception_and_controller_returns_422(): void
    {
        // Self-order attempt via HTTP POST /orders
        $response = $this->actingAs($this->idol)->postJson(route('orders.store'), [
            'idol_id' => $this->idol->id,
            'services' => [['id' => $this->service->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Нельзя заказать услуги у самого себя']);
    }

    public function test_content_pack_purchase_rejects_soft_deleted_author(): void
    {
        $pack = ContentPack::create([
            'user_id' => $this->idol->id,
            'title' => 'Эксклюзивный пак',
            'price' => 500,
            'status' => 'published',
        ]);

        // Soft-delete the author
        $this->idol->delete();
        $this->assertTrue($this->idol->trashed());

        // Controller check: whereHas('user', fn ($q) => $q->whereNull('deleted_at')->where('is_banned', false))
        $response = $this->actingAs($this->customer)->postJson(route('content-packs.purchase'), [
            'items' => [$pack->id],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Один или несколько контент-паков недоступны для покупки.']);

        // WalletService check directly
        $walletService = app(WalletService::class);
        $packWithTrashedAuthor = ContentPack::withTrashed()->with('user')->find($pack->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Автор контент-пака недоступен.');
        $walletService->purchaseContentPack($this->customer, $packWithTrashedAuthor);
    }

    public function test_content_pack_purchase_validates_items_count_and_integers(): void
    {
        // items array cannot exceed 50
        $overLimit = array_fill(0, 51, 1);
        $response = $this->actingAs($this->customer)->postJson(route('content-packs.purchase'), [
            'items' => $overLimit,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);

        // items must be integers >= 1
        $responseInvalid = $this->actingAs($this->customer)->postJson(route('content-packs.purchase'), [
            'items' => [0],
        ]);
        $responseInvalid->assertStatus(422);
        $responseInvalid->assertJsonValidationErrors(['items.0']);
    }

    public function test_round_fee_helper_uses_half_up_banking_rounding(): void
    {
        $walletService = app(WalletService::class);

        // 12.55 with 10% fee = 1.255 -> rounds to 1.26 under PHP_ROUND_HALF_UP (whereas bcdiv was 1.25)
        $fee1 = $walletService->roundFee(12.55, 10.0);
        $this->assertSame(1.26, $fee1);

        // 100 with 10% fee = 10.00
        $fee2 = $walletService->roundFee(100.0, 10.0);
        $this->assertSame(10.0, $fee2);

        // 0 or negative fee percent
        $fee3 = $walletService->roundFee(100.0, 0.0);
        $this->assertSame(0.0, $fee3);
    }

    public function test_wallet_fillable_excludes_balance_and_held_balance(): void
    {
        $wallet = new Wallet;
        $fillable = $wallet->getFillable();

        $this->assertNotContains('balance', $fillable);
        $this->assertNotContains('held_balance', $fillable);
        $this->assertContains('user_id', $fillable);
        $this->assertContains('currency', $fillable);
        $this->assertContains('is_active', $fillable);
    }

    public function test_order_and_order_item_casts(): void
    {
        $order = new Order;
        $orderCasts = $order->getCasts();
        $this->assertSame('boolean', $orderCasts['completion_confirmed_by_idol'] ?? null);
        $this->assertSame('boolean', $orderCasts['completion_confirmed_by_customer'] ?? null);

        $orderItem = new OrderItem;
        $itemCasts = $orderItem->getCasts();
        $this->assertSame('integer', $itemCasts['price'] ?? null);
        $this->assertSame('integer', $itemCasts['quantity'] ?? null);
    }

    public function test_wallet_transaction_reference_morph_includes_trashed(): void
    {
        $pack = ContentPack::create([
            'user_id' => $this->idol->id,
            'title' => 'Архивный пак',
            'price' => 500,
            'status' => 'published',
        ]);

        $wallet = app(WalletService::class)->getOrCreateWallet($this->idol);

        $tx = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $this->idol->id,
            'type' => \App\Enums\WalletTransactionType::PackSale,
            'amount' => 500,
            'balance_before' => 0,
            'balance_after' => 500,
            'status' => \App\Enums\WalletTransactionStatus::Completed,
            'reference_type' => ContentPack::class,
            'reference_id' => $pack->id,
        ]);

        // Soft-delete the pack
        $pack->delete();
        $this->assertTrue($pack->trashed());

        // Fresh transaction reference still loads the soft-deleted pack
        $txFresh = WalletTransaction::find($tx->id);
        $this->assertNotNull($txFresh->reference);
        $this->assertSame($pack->id, $txFresh->reference->id);
    }

    public function test_content_pack_has_wallet_transactions_morph_relation(): void
    {
        $pack = ContentPack::create([
            'user_id' => $this->idol->id,
            'title' => 'Пак для связи',
            'price' => 500,
            'status' => 'published',
        ]);

        $wallet = app(WalletService::class)->getOrCreateWallet($this->idol);

        $tx = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $this->idol->id,
            'type' => \App\Enums\WalletTransactionType::PackSale,
            'amount' => 500,
            'balance_before' => 0,
            'balance_after' => 500,
            'status' => \App\Enums\WalletTransactionStatus::Completed,
            'reference_type' => ContentPack::class,
            'reference_id' => $pack->id,
        ]);

        $this->assertTrue($pack->walletTransactions()->where('id', $tx->id)->exists());
    }
}
