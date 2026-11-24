<?php

use App\Enums\WalletTransactionType;
use App\Models\ChargebackClaim;
use App\Models\CoinPack;
use App\Models\CoinPackPurchase;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\CoinPackSeeder;
use Database\Seeders\PlatformSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(PlatformSettingSeeder::class);
    $this->seed(CoinPackSeeder::class);
});

test('visible coin packs return exactly 4 approved Mandarin BaaS packages', function () {
    $packs = CoinPack::visible()->get();

    expect($packs)->toHaveCount(4);

    $starter = $packs->firstWhere('is_starter', true);
    expect($starter)->not->toBeNull();
    expect((float) $starter->coins)->toBe(300.00);
    expect($starter->sort_order)->toBe(0);

    $regularCoins = $packs->where('is_starter', false)->pluck('coins')->map(fn ($c) => (float) $c)->values()->all();
    expect($regularCoins)->toBe([500.00, 700.00, 1000.00]);
});

test('client can purchase coin pack with 0% acquiring fee via Mandarin invoice', function () {
    $client = User::factory()->create(['is_idol' => false]);
    $pack = CoinPack::where('coins', 500.00)->firstOrFail();

    $response = $this->actingAs($client)
        ->postJson(route('wallet.deposit_pack'), [
            'coin_pack_id' => $pack->id,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'balance' => 500.0,
        ])
        ->assertJsonStructure([
            'payment_url',
            'invoice_id',
        ]);

    expect((float) $client->wallet->fresh()->balance)->toBe(500.00);

    $tx = WalletTransaction::where('wallet_id', $client->wallet->id)->latest('id')->first();
    expect($tx)->not->toBeNull();
    expect($tx->type)->toBe(WalletTransactionType::Deposit);
    expect((float) $tx->amount)->toBe(500.00);
    expect($tx->metadata['gateway'])->toBe('mandarin');
    expect((float) $tx->metadata['fee_amount'])->toBe(0.00);
});

test('starter coin pack can be purchased only once per user', function () {
    $client = User::factory()->create(['is_idol' => false]);
    $starterPack = CoinPack::where('is_starter', true)->firstOrFail();

    // First purchase succeeds
    $res1 = $this->actingAs($client)
        ->postJson(route('wallet.deposit_pack'), [
            'coin_pack_id' => $starterPack->id,
        ]);

    $res1->assertOk()
        ->assertJson([
            'success' => true,
            'balance' => 300.0,
            'starter_used' => true,
        ]);

    expect(CoinPackPurchase::where('user_id', $client->id)->where('is_starter', true)->exists())->toBeTrue();

    // Second purchase attempt is rejected with 422
    $res2 = $this->actingAs($client)
        ->postJson(route('wallet.deposit_pack'), [
            'coin_pack_id' => $starterPack->id,
        ]);

    $res2->assertStatus(422)
        ->assertJson([
            'error' => 'Стартовый пакет можно приобрести только один раз.',
        ]);

    expect((float) $client->wallet->fresh()->balance)->toBe(300.00);
});

test('client cash-out is strictly prohibited and returns 403 Forbidden under 115-FZ', function () {
    $client = User::factory()->create(['is_idol' => false]);
    $client->getOrCreateWallet()->forceFill(['balance' => 5000.00])->save();

    $response = $this->actingAs($client)
        ->postJson(route('wallet.withdraw'), [
            'amount' => 1000.00,
        ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Вывод средств доступен только айдолам.',
        ]);

    expect((float) $client->wallet->fresh()->balance)->toBe(5000.00);
});

test('idol without verified self-employed status cannot withdraw funds', function () {
    $idol = User::factory()->create([
        'is_idol' => true,
        'tax_status' => 'none',
        'tax_verified_at' => null,
        'is_payout_ready' => false,
    ]);
    $idol->getOrCreateWallet()->forceFill(['pink_balance' => 5000.00])->save();

    $response = $this->actingAs($idol)
        ->postJson(route('wallet.withdraw'), [
            'amount' => 1000.00,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Для вывода средств необходимо подключить статус самозанятого (422-ФЗ).',
        ]);
});

test('idol without configured payout phone cannot withdraw funds', function () {
    $idol = User::factory()->create([
        'is_idol' => true,
        'tax_status' => 'self_employed',
        'tax_verified_at' => now(),
        'is_payout_ready' => false,
        'payout_phone' => null,
    ]);
    $idol->getOrCreateWallet()->forceFill(['pink_balance' => 5000.00])->save();

    $response = $this->actingAs($idol)
        ->postJson(route('wallet.withdraw'), [
            'amount' => 1000.00,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Реквизиты для выплат по СБП не настроены.',
        ]);
});

test('idol cannot withdraw amount below minimum payout threshold 1000 RUB', function () {
    $idol = User::factory()->create([
        'is_idol' => true,
        'tax_status' => 'self_employed',
        'tax_verified_at' => now(),
        'is_payout_ready' => true,
        'payout_phone' => '+79991234567',
        'sbp_bank_id' => '100000000004',
    ]);
    $idol->getOrCreateWallet()->forceFill(['pink_balance' => 5000.00])->save();

    $response = $this->actingAs($idol)
        ->postJson(route('wallet.withdraw'), [
            'amount' => 500.00,
        ]);

    $response->assertStatus(422);
});

test('idol cannot withdraw amount exceeding available limit reduced by chargeback claims', function () {
    $idol = User::factory()->create([
        'is_idol' => true,
        'tax_status' => 'self_employed',
        'tax_verified_at' => now(),
        'is_payout_ready' => true,
        'payout_phone' => '+79991234567',
        'sbp_bank_id' => '100000000004',
    ]);
    // Pink balance is 2500, but has chargeback recourse claim of 1700 => available is only 800
    $idol->getOrCreateWallet()->forceFill(['pink_balance' => 2500.00])->save();

    ChargebackClaim::create([
        'idol_id' => $idol->id,
        'amount' => 1700.00,
        'recovered_amount' => 0.00,
        'status' => 'pending',
    ]);

    expect((float) $idol->getOrCreateWallet()->available_for_payout)->toBe(800.00);

    // Attempting to withdraw 1000 is rejected
    $response = $this->actingAs($idol)
        ->postJson(route('wallet.withdraw'), [
            'amount' => 1000.00,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Сумма вывода превышает доступный лимит с учётом долговых обязательств.',
        ]);
});

test('verified self-employed idol can withdraw via SBP with 0% fee', function () {
    $idol = User::factory()->create([
        'is_idol' => true,
        'tax_status' => 'self_employed',
        'tax_verified_at' => now(),
        'is_payout_ready' => true,
        'payout_phone' => '+79991234567',
        'sbp_bank_id' => '100000000004',
    ]);
    $idol->getOrCreateWallet()->forceFill(['pink_balance' => 3000.00])->save();

    $response = $this->actingAs($idol)
        ->postJson(route('wallet.withdraw'), [
            'amount' => 1500.00,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'pink_balance' => 1500.0,
            'available_for_payout' => 1500.0,
        ]);

    expect((float) $idol->wallet->fresh()->pink_balance)->toBe(1500.00);

    $tx = WalletTransaction::where('wallet_id', $idol->wallet->id)->latest('id')->first();
    expect($tx)->not->toBeNull();
    expect($tx->type)->toBe(WalletTransactionType::Withdrawal);
    expect((float) $tx->amount)->toBe(-1500.00);
    expect($tx->metadata['method'])->toBe('sbp');
    expect($tx->metadata['payout_phone'])->toBe('+79991234567');
    expect((float) $tx->metadata['fee_amount'])->toBe(0.00);
});
