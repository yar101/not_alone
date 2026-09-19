<?php

namespace Tests\Feature;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Events\WalletBalanceUpdated;
use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function createAdmin(): Admin
{
    return Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@test.local',
        'password' => bcrypt('password'),
    ]);
}

it('allows admin to view transactions table with filters and kpi', function () {
    $admin = createAdmin();
    $user1 = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);
    $service = app(WalletService::class);

    $service->deposit($user1, 1000.00, 'Депозит Алисы');
    $service->deposit($user2, 2000.00, 'Депозит Боба');

    $response = $this->actingAs($admin, 'admin')->get(route('admin.transactions.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Transactions/Index')
        ->has('transactions.data', 2)
        ->has('kpi')
        ->where('kpi.total_count', 2)
        ->has('typeCounts')
    );
});

it('filters transactions by user_id', function () {
    $admin = createAdmin();
    $user1 = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);
    $service = app(WalletService::class);

    $service->deposit($user1, 1000.00, 'Депозит 1');
    $service->deposit($user2, 2000.00, 'Депозит 2');

    $response = $this->actingAs($admin, 'admin')->get(route('admin.transactions.index', ['user_id' => $user1->id]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Transactions/Index')
        ->has('transactions.data', 1)
        ->where('transactions.data.0.user.id', $user1->id)
        ->where('selectedUser.id', $user1->id)
    );
});

it('returns transaction details in show endpoint', function () {
    $admin = createAdmin();
    $user = User::factory()->create(['name' => 'Alice']);
    $service = app(WalletService::class);

    $tx = $service->deposit($user, 750.00, 'Тестовая транзакция');

    $response = $this->actingAs($admin, 'admin')->get(route('admin.transactions.show', $tx->id));

    $response->assertOk();
    $response->assertJson([
        'id' => $tx->id,
        'amount' => 750.00,
        'type' => 'deposit',
        'status' => 'completed',
        'user' => [
            'id' => $user->id,
            'name' => 'Alice',
        ],
    ]);
});

it('allows admin to credit user wallet via manual transaction', function () {
    Event::fake([WalletBalanceUpdated::class]);
    $admin = createAdmin();
    $user = User::factory()->create();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.transactions.store'), [
        'user_id' => $user->id,
        'direction' => 'credit',
        'type' => 'admin_adjustment',
        'amount' => 1500.50,
        'description' => 'Бонус за активность',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $wallet = $user->wallet->fresh();
    expect((float) $wallet->balance)->toBe(1500.50);

    $tx = WalletTransaction::where('user_id', $user->id)->first();
    expect($tx)->not->toBeNull();
    expect((float) $tx->amount)->toBe(1500.50);
    expect($tx->type)->toBe(WalletTransactionType::AdminAdjustment);
    expect($tx->description)->toBe('Бонус за активность');
    expect($tx->metadata['admin_id'])->toBe($admin->id);

    // Assert audit log was recorded
    expect(AdminLog::where('admin_id', $admin->id)->where('action', 'wallet_adjustment')->exists())->toBeTrue();

    // Assert WebSocket event was broadcast
    Event::assertDispatched(WalletBalanceUpdated::class, function ($e) use ($user) {
        return $e->userId === $user->id && $e->balance === 1500.50;
    });
});

it('allows admin to debit user wallet with sufficient balance and prevents overdraft', function () {
    $admin = createAdmin();
    $user = User::factory()->create();
    $service = app(WalletService::class);

    // Initial deposit of 1000
    $service->deposit($user, 1000.00, 'Начальный баланс');

    // Successful debit of 400
    $response = $this->actingAs($admin, 'admin')->post(route('admin.transactions.store'), [
        'user_id' => $user->id,
        'direction' => 'debit',
        'type' => 'admin_adjustment',
        'amount' => 400.00,
        'description' => 'Корректировка баланса',
    ]);

    $response->assertSessionHasNoErrors();
    expect((float) $user->wallet->fresh()->balance)->toBe(600.00);

    // Overdraft debit attempt (trying to debit 700 when available is 600)
    $failResponse = $this->actingAs($admin, 'admin')->post(route('admin.transactions.store'), [
        'user_id' => $user->id,
        'direction' => 'debit',
        'type' => 'admin_adjustment',
        'amount' => 700.00,
        'description' => 'Неудачное списание',
    ]);

    $failResponse->assertSessionHasErrors('amount');
    expect((float) $user->wallet->fresh()->balance)->toBe(600.00);
});

it('allows admin to configure fee percentages in settings and applies them', function () {
    $admin = createAdmin();

    $settingsData = [
        'rating_low_threshold' => 30,
        'order_auto_complete_delay' => 72,
        'content_pack_price_min' => 100,
        'content_pack_price_max' => 10000,
        'moderate_new_packs' => true,
        'moderate_existing_packs' => false,
        'deposit_fee_percent' => 2.0,
        'withdrawal_fee_percent' => 5.0,
        'platform_fee_percent' => 12.5,
        'rating_deltas' => [
            'review_5star' => 0.8,
            'review_4star' => 0.4,
            'review_2star' => -0.5,
            'review_1star' => -1.2,
            'order_completed' => 0.2,
            'report_accepted' => -2.0,
            'review_dispute_approved' => 0.6,
        ],
    ];

    $response = $this->actingAs($admin, 'admin')->patch(route('admin.settings.update'), $settingsData);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    expect((float) PlatformSetting::get('deposit_fee_percent'))->toBe(2.0);
    expect((float) PlatformSetting::get('withdrawal_fee_percent'))->toBe(5.0);
    expect((float) PlatformSetting::get('platform_fee_percent'))->toBe(12.5);

    // Verify deposit applies 2% fee
    $user = User::factory()->create();
    $service = app(WalletService::class);
    $tx = $service->deposit($user, 1000.00, 'Тестовый депозит с комиссией');

    // 1000 - 2% (20) = 980 net credited
    expect((float) $tx->amount)->toBe(980.00);
    expect((float) $user->wallet->fresh()->balance)->toBe(980.00);
    expect((float) $tx->metadata['gross_amount'])->toBe(1000.0);
    expect((float) $tx->metadata['fee_amount'])->toBe(20.0);
    expect((float) $tx->metadata['net_amount'])->toBe(980.0);

    // Verify withdraw applies 5% fee
    $idol = User::factory()->create(['is_idol' => true]);
    $service->deposit($idol, 2000.00, 'Баланс айдола'); // with 2% fee: 1960.00
    $wTx = $service->withdraw($idol, 500.00);

    expect((float) $wTx->amount)->toBe(-500.00);
    expect((float) $wTx->metadata['fee_amount'])->toBe(25.0);
    expect((float) $wTx->metadata['payout_amount'])->toBe(475.0);
});
