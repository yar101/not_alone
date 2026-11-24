<?php

use App\Enums\OrderStatus;
use App\Models\ChargebackClaim;
use App\Models\ProcessedWebhookEvent;
use App\Models\TaxIdCooldown;
use App\Models\User;
use Illuminate\Database\QueryException;

test('OrderStatus enum supports Closed status and terminal behavior', function () {
    expect(OrderStatus::Closed->value)->toBe('closed')
        ->and(OrderStatus::Closed->label())->toBe('Закрыт')
        ->and(OrderStatus::Closed->isTerminal())->toBeTrue();

    // Valid transitions
    expect(OrderStatus::Completed->canTransitionTo(OrderStatus::Closed))->toBeTrue()
        ->and(OrderStatus::Disputed->canTransitionTo(OrderStatus::Closed))->toBeTrue();

    // Closed is terminal - cannot transition anywhere
    expect(OrderStatus::Closed->canTransitionTo(OrderStatus::Pending))->toBeFalse()
        ->and(OrderStatus::Closed->canTransitionTo(OrderStatus::Disputed))->toBeFalse()
        ->and(OrderStatus::Closed->canTransitionTo(OrderStatus::Refunded))->toBeFalse();
});

test('ChargebackClaim tracks recourse debt correctly', function () {
    $idol = User::factory()->create(['is_idol' => true]);

    $claim = ChargebackClaim::create([
        'idol_id' => $idol->id,
        'amount' => 5000.00,
        'recovered_amount' => 1200.00,
        'status' => 'pending',
    ]);

    expect($claim->remaining_debt)->toBe(3800.00)
        ->and($claim->idol->id)->toBe($idol->id);

    // Refresh user relation
    expect($idol->chargeback_debt)->toBe(3800.00);
});

test('TaxIdCooldown correctly validates cooldown period', function () {
    $idol = User::factory()->create(['is_idol' => true]);

    $cooldown = TaxIdCooldown::create([
        'inn' => '770123456789',
        'user_id' => $idol->id,
        'revoked_at' => now(),
        'cooldown_until' => now()->addDays(365),
        'reason' => 'Добровольное закрытие профиля айдола',
    ]);

    expect($cooldown->isUnderCooldown())->toBeTrue();

    $expired = TaxIdCooldown::create([
        'inn' => '770987654321',
        'user_id' => $idol->id,
        'revoked_at' => now()->subDays(400),
        'cooldown_until' => now()->subDays(35),
    ]);

    expect($expired->isUnderCooldown())->toBeFalse();
});

test('ProcessedWebhookEvent ensures idempotency', function () {
    ProcessedWebhookEvent::create([
        'event_id' => 'evt_test_123',
        'event_type' => 'invoice.paid',
        'payload' => ['amount' => 1000],
        'processed_at' => now(),
    ]);

    expect(function () {
        ProcessedWebhookEvent::create([
            'event_id' => 'evt_test_123',
            'event_type' => 'invoice.paid',
            'payload' => ['amount' => 1000],
            'processed_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

test('Wallet available_for_payout takes chargeback debt into account', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $wallet = $idol->getOrCreateWallet();

    $wallet->pink_balance = 5000.00;
    $wallet->save();

    expect($wallet->available_for_payout)->toBe(5000.00);

    // Add a chargeback claim of 2000
    ChargebackClaim::create([
        'idol_id' => $idol->id,
        'amount' => 2000.00,
        'recovered_amount' => 0.00,
        'status' => 'pending',
    ]);

    $wallet->refresh();
    expect($wallet->available_for_payout)->toBe(3000.00);

    // Debt exceeds pink balance -> available is 0, never negative
    ChargebackClaim::create([
        'idol_id' => $idol->id,
        'amount' => 4000.00,
        'recovered_amount' => 0.00,
        'status' => 'pending',
    ]);

    $wallet->refresh();
    expect($wallet->available_for_payout)->toBe(0.00);
});

test('PostgreSQL enforces non-negative CHECK constraints on wallet balances', function () {
    $user = User::factory()->create();
    $wallet = $user->getOrCreateWallet();

    expect(function () use ($wallet) {
        \Illuminate\Support\Facades\DB::table('wallets')
            ->where('id', $wallet->id)
            ->update(['pink_balance' => -100.00]);
    })->toThrow(QueryException::class);

    expect(function () use ($wallet) {
        \Illuminate\Support\Facades\DB::table('wallets')
            ->where('id', $wallet->id)
            ->update(['balance' => -50.00]);
    })->toThrow(QueryException::class);
});
