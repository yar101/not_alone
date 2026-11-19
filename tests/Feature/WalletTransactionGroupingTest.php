<?php

namespace Tests\Feature;

use App\Enums\WalletTransactionStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Builds a 28-row ledger where order blocks straddle the 20-row page boundary:
 * page 1 window is the newest 20 rows, page 2 window is the remaining 8 (rows 7..0).
 *
 * @return array{customer: User, orders: array<string, Order>, rows: array<int, WalletTransaction>}
 */
function seedWalletGroupingFixture(): array
{
    $customer = User::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $orders = [
        'straddling' => Order::factory()->create(['customer_id' => $customer->id]),
        'three_rows' => Order::factory()->create(['customer_id' => $customer->id]),
        'two_rows' => Order::factory()->create(['customer_id' => $customer->id]),
        'single_row' => Order::factory()->create(['customer_id' => $customer->id]),
    ];

    $create = function (string $type, float $amount, ?Order $order, string $description) use ($wallet, $customer) {
        return WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $customer->id,
            'type' => $type,
            'currency' => 'blue',
            'amount' => $amount,
            'balance_before' => 1000,
            'balance_after' => 1000 + $amount,
            'held_balance_before' => 0,
            'held_balance_after' => 0,
            'status' => WalletTransactionStatus::Completed,
            'reference_type' => $order ? Order::class : null,
            'reference_id' => $order?->id,
            'idempotency_key' => 'grouping_'.uniqid('', true),
            'description' => $description,
            'metadata' => $order ? ['order_id' => $order->id] : null,
        ]);
    };

    $rows = [
        // Oldest row of the straddling block — lands on page 2, must be pulled onto page 1.
        $create('order_hold', -400, $orders['straddling'], 'Оплата услуг по заказу'),
        $create('customer_service_fee', -100, $orders['three_rows'], 'Сервисный сбор'),
        $create('order_hold', -2000, $orders['three_rows'], 'Оплата услуг по заказу'),
        $create('order_hold_release', 0, $orders['three_rows'], 'Списание из заморозки'),
        $create('order_hold', -500, $orders['two_rows'], 'Оплата услуг по заказу'),
        $create('order_refund', 500, $orders['two_rows'], 'Возврат средств по заказу'),
        $create('order_hold', -300, $orders['single_row'], 'Оплата услуг по заказу'),
        $create('deposit', 5000, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        $create('deposit', 10, null, 'Пополнение'),
        // Newest row of the straddling block — owns the block on page 1.
        $create('order_refund', 400, $orders['straddling'], 'Возврат средств по заказу'),
    ];

    return compact('customer', 'orders', 'rows');
}

it('pulls the whole order block onto the page owning its newest row', function () {
    $fixture = seedWalletGroupingFixture();
    $rows = $fixture['rows'];
    $orderId = $fixture['orders']['straddling']->id;

    $response = $this->actingAs($fixture['customer'])
        ->get('/wallet?tab=history&filter=all');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Wallet/Index')
        ->has('transactions.data', 20)
        // 20 window rows + the older row of the straddling block pulled from page 2.
        ->has('displayTransactions', 21)
        ->where('displayTransactions.0.id', $rows[27]->id)
        ->where('displayTransactions.0.order_id', $orderId)
        ->where('displayTransactions.19.id', $rows[8]->id)
        ->where('displayTransactions.20.id', $rows[0]->id)
        ->where('displayTransactions.20.order_id', $orderId)
    );
});

it('hides rows of blocks owned by another page and keeps order_id serialization', function () {
    $fixture = seedWalletGroupingFixture();
    $rows = $fixture['rows'];
    $straddlingId = $fixture['orders']['straddling']->id;

    $firstPage = $this->actingAs($fixture['customer'])
        ->get('/wallet?tab=history&filter=all');

    $nextUrl = null;
    $firstPage->assertInertia(function (Assert $page) use (&$nextUrl) {
        $nextUrl = $page->toArray()['props']['transactions']['next_page_url'];
    });

    expect($nextUrl)->not->toBeNull();

    $secondPage = $this->actingAs($fixture['customer'])->get($nextUrl);

    $secondPage->assertInertia(fn (Assert $page) => $page
        ->has('transactions.data', 8)
        // 8 window rows minus the straddling block row, which is shown whole on page 1.
        ->has('displayTransactions', 7)
        ->where('displayTransactions.0.id', $rows[7]->id)
        ->where('displayTransactions.1.id', $rows[6]->id)
        // Single-row block keeps its order_id and stays a standalone row.
        ->where('displayTransactions.1.order_id', $fixture['orders']['single_row']->id)
        // Block of the two-row order: hold then refund, both on this page.
        ->where('displayTransactions.2.id', $rows[5]->id)
        ->where('displayTransactions.2.order_id', $fixture['orders']['two_rows']->id)
        ->where('displayTransactions.3.id', $rows[4]->id)
        ->where('displayTransactions.3.order_id', $fixture['orders']['two_rows']->id)
        // Block of the three-row order: fee, hold and hold release.
        ->where('displayTransactions.4.id', $rows[3]->id)
        ->where('displayTransactions.4.order_id', $fixture['orders']['three_rows']->id)
        ->where('displayTransactions.5.id', $rows[2]->id)
        ->where('displayTransactions.6.id', $rows[1]->id)
        ->where('displayTransactions.6.order_id', $fixture['orders']['three_rows']->id)
        ->where('displayTransactions', fn ($displayRows) => collect($displayRows)->every(fn ($row) => $row['order_id'] !== $straddlingId))
    );
});
