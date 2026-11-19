<?php

namespace Tests\Feature;

use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\CoinPack;
use App\Models\CoinPackPurchase;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    CoinPack::query()->forceDelete();
    PlatformSetting::set('deposit_fee_percent', '4.0');
});

it('allows user to purchase an active regular coin pack with deposit fee applied', function () {
    $user = User::factory()->create(['is_idol' => false]);
    $pack = CoinPack::create([
        'coins' => 500.00,
        'is_visible' => true,
        'is_starter' => false,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->postJson(route('wallet.deposit_pack'), [
        'coin_pack_id' => $pack->id,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'balance' => 500.00, // exact coin amount credited: 500.00
            'starter_used' => false,
        ]);

    $user->refresh();
    expect((float) $user->wallet->balance)->toBe(500.00);

    $purchase = CoinPackPurchase::where('user_id', $user->id)->first();
    expect($purchase)->not->toBeNull()
        ->and((float) $purchase->coins)->toBe(500.00)
        ->and((float) $purchase->fee_amount)->toBe(20.00) // 4% markup
        ->and((float) $purchase->net_amount)->toBe(500.00)
        ->and($purchase->is_starter)->toBeFalse()
        ->and($purchase->coin_pack_id)->toBe($pack->id);

    $tx = WalletTransaction::where('user_id', $user->id)->where('type', WalletTransactionType::Deposit)->first();
    expect($tx)->not->toBeNull()
        ->and((float) $tx->amount)->toBe(500.00)
        ->and($tx->metadata['coin_pack_id'])->toBe($pack->id)
        ->and((float) $tx->metadata['total_paid_rub'])->toBe(520.00)
        ->and((float) $tx->metadata['fee_amount'])->toBe(20.00)
        ->and($tx->metadata['is_starter'])->toBeFalse();
});

it('allows purchasing a starter pack only once per account', function () {
    $user = User::factory()->create(['is_idol' => false]);
    $starterPack = CoinPack::create([
        'coins' => 100.00,
        'is_visible' => true,
        'is_starter' => true,
        'sort_order' => 0,
    ]);

    // First purchase succeeds
    $response1 = $this->actingAs($user)->postJson(route('wallet.deposit_pack'), [
        'coin_pack_id' => $starterPack->id,
    ]);

    $response1->assertOk()
        ->assertJson([
            'success' => true,
            'balance' => 100.00, // exact coin amount credited: 100.00
            'starter_used' => true,
        ]);

    // Second purchase attempt fails
    $response2 = $this->actingAs($user)->postJson(route('wallet.deposit_pack'), [
        'coin_pack_id' => $starterPack->id,
    ]);

    $response2->assertStatus(422)
        ->assertJson(['error' => 'Стартовый пакет можно приобрести только один раз.']);

    expect((float) $user->fresh()->wallet->balance)->toBe(100.00);
    expect(CoinPackPurchase::where('user_id', $user->id)->count())->toBe(1);
});

it('blocks purchasing hidden coin packs', function () {
    $user = User::factory()->create(['is_idol' => false]);
    $hiddenPack = CoinPack::create([
        'coins' => 1000.00,
        'is_visible' => false,
        'is_starter' => false,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->postJson(route('wallet.deposit_pack'), [
        'coin_pack_id' => $hiddenPack->id,
    ]);

    $response->assertStatus(422)
        ->assertJson(['error' => 'Выбранный пакет недоступен для покупки.']);
});

it('preserves starter pack restriction even if starter pack was deleted and a new one created', function () {
    $user = User::factory()->create(['is_idol' => false]);
    $oldStarter = CoinPack::create([
        'coins' => 100.00,
        'is_visible' => true,
        'is_starter' => true,
        'sort_order' => 0,
    ]);

    // User buys old starter pack
    $this->actingAs($user)->postJson(route('wallet.deposit_pack'), [
        'coin_pack_id' => $oldStarter->id,
    ])->assertOk();

    // Admin soft-deletes old starter pack and creates a new starter pack
    $oldStarter->delete();
    expect($oldStarter->trashed())->toBeTrue();

    $newStarter = CoinPack::create([
        'coins' => 150.00,
        'is_visible' => true,
        'is_starter' => true,
        'sort_order' => 0,
    ]);

    // User tries to buy the new starter pack
    $response = $this->actingAs($user)->postJson(route('wallet.deposit_pack'), [
        'coin_pack_id' => $newStarter->id,
    ]);

    $response->assertStatus(422)
        ->assertJson(['error' => 'Стартовый пакет можно приобрести только один раз.']);
});

it('returns visible coin packs and starterUsed flag in wallet endpoints', function () {
    $user = User::factory()->create(['is_idol' => false]);

    CoinPack::create(['coins' => 100.00, 'is_visible' => true, 'is_starter' => true, 'sort_order' => 0]);
    CoinPack::create(['coins' => 300.00, 'is_visible' => true, 'is_starter' => false, 'sort_order' => 1]);
    CoinPack::create(['coins' => 999.00, 'is_visible' => false, 'is_starter' => false, 'sort_order' => 2]);

    $response = $this->actingAs($user)->getJson(route('wallet.data'));

    $response->assertOk()
        ->assertJsonStructure([
            'wallet',
            'transactions',
            'coinPacks',
            'starterUsed',
        ]);

    $data = $response->json();
    expect(count($data['coinPacks']))->toBe(2)
        ->and($data['starterUsed'])->toBeFalse();
});

it('allows admin to manage coin packs and prevents duplicate starter packs', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin_packs@test.com',
        'password' => 'secret123',
    ]);

    // 1. Admin creates first starter pack
    $response1 = $this->actingAs($admin, 'admin')->post(route('admin.coin-packs.store'), [
        'coins' => 100.00,
        'is_visible' => true,
        'is_starter' => true,
        'sort_order' => 0,
    ]);
    $response1->assertRedirect();
    $pack1 = CoinPack::where('coins', 100.00)->first();
    expect($pack1)->not->toBeNull()->and($pack1->is_starter)->toBeTrue();

    // 2. Admin cannot create another starter pack while active one exists
    $response2 = $this->actingAs($admin, 'admin')->post(route('admin.coin-packs.store'), [
        'coins' => 200.00,
        'is_visible' => true,
        'is_starter' => true,
        'sort_order' => 1,
    ]);
    $response2->assertSessionHasErrors(['is_starter']);

    // 3. Admin creates regular pack
    $response3 = $this->actingAs($admin, 'admin')->post(route('admin.coin-packs.store'), [
        'coins' => 500.00,
        'is_visible' => true,
        'is_starter' => false,
        'sort_order' => 2,
    ]);
    $response3->assertRedirect();
    $pack2 = CoinPack::where('coins', 500.00)->first();
    expect($pack2)->not->toBeNull()->and($pack2->is_starter)->toBeFalse();

    // 4. Admin updates visibility and order
    $response4 = $this->actingAs($admin, 'admin')->patch(route('admin.coin-packs.update', $pack2->id), [
        'coins' => 600.00,
        'is_visible' => false,
        'is_starter' => false,
        'sort_order' => 5,
    ]);
    $response4->assertRedirect();
    expect((float) $pack2->fresh()->coins)->toBe(600.00)
        ->and($pack2->fresh()->is_visible)->toBeFalse()
        ->and($pack2->fresh()->sort_order)->toBe(5);

    // 5. Admin soft-deletes pack
    $response5 = $this->actingAs($admin, 'admin')->delete(route('admin.coin-packs.destroy', $pack2->id));
    $response5->assertRedirect();
    expect($pack2->fresh()->trashed())->toBeTrue();
});

it('passes coinPacks and starterUsed props to Wallet/Index page', function () {
    $user = User::factory()->create(['is_idol' => false]);
    CoinPack::create(['coins' => 100.00, 'is_visible' => true, 'is_starter' => true, 'sort_order' => 0]);
    CoinPack::create(['coins' => 300.00, 'is_visible' => true, 'is_starter' => false, 'sort_order' => 1]);
    CoinPack::create(['coins' => 999.00, 'is_visible' => false, 'is_starter' => false, 'sort_order' => 2]);

    $response = $this->actingAs($user)->get(route('wallet.show'));

    $response->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Wallet/Index')
            ->has('coinPacks', 2)
            ->where('starterUsed', false)
        );
});

it('passes all coinPacks to Admin/Settings page including hidden ones', function () {
    $admin = Admin::create([
        'name' => 'Admin Settings',
        'email' => 'admin_settings_test@test.com',
        'password' => 'secret123',
    ]);

    CoinPack::create(['coins' => 100.00, 'is_visible' => true, 'is_starter' => true, 'sort_order' => 0]);
    CoinPack::create(['coins' => 300.00, 'is_visible' => false, 'is_starter' => false, 'sort_order' => 1]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.settings.index'));

    $response->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Admin/Settings')
            ->has('coinPacks', 2)
        );
});
