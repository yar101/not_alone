<?php

use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AdminLogService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guest cannot access admin audit logs', function () {
    $response = $this->get(route('admin.logs.index'));
    $response->assertRedirect(route('admin.login'));
});

test('regular user cannot access admin audit logs', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.logs.index'));
    $response->assertRedirect(route('admin.login'));
});

test('admin can view audit logs', function () {
    $admin = Admin::create([
        'name' => 'Admin Auditor',
        'email' => 'auditor@test.local',
        'password' => bcrypt('password'),
    ]);

    AdminLog::create([
        'admin_id' => $admin->id,
        'action' => 'ban_user',
        'target_type' => 'user',
        'target_id' => '123',
        'details' => ['reason' => 'Spamming in public chat'],
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.logs.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Logs/Index')
        ->has('logs.data', 1)
        ->where('logs.data.0.action', 'ban_user')
        ->where('logs.data.0.target_type', 'user')
        ->where('logs.data.0.target_id', 123)
    );
});

test('admin can filter audit logs by target_type and date range', function () {
    $admin = Admin::create([
        'name' => 'Admin Auditor',
        'email' => 'auditor2@test.local',
        'password' => bcrypt('password'),
    ]);

    AdminLog::create([
        'admin_id' => $admin->id,
        'action' => 'ban_user',
        'target_type' => 'user',
        'target_id' => '1',
        'details' => ['reason' => 'test'],
        'created_at' => now()->subDays(5),
    ]);

    AdminLog::create([
        'admin_id' => $admin->id,
        'action' => 'resolve_dispute',
        'target_type' => 'dispute',
        'target_id' => '2',
        'details' => ['decision' => 'refund'],
        'created_at' => now(),
    ]);

    // Filter by target_type
    $response = $this->actingAs($admin, 'admin')->get(route('admin.logs.index', [
        'target_type' => 'dispute',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Logs/Index')
        ->has('logs.data', 1)
        ->where('logs.data.0.target_type', 'dispute')
    );

    // Filter by date range (only logs from today)
    $responseDate = $this->actingAs($admin, 'admin')->get(route('admin.logs.index', [
        'date_from' => now()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $responseDate->assertOk();
    $responseDate->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Logs/Index')
        ->has('logs.data', 1)
        ->where('logs.data.0.target_type', 'dispute')
    );
});

test('admin can search audit logs by details content', function () {
    $admin = Admin::create([
        'name' => 'Admin Auditor',
        'email' => 'auditor3@test.local',
        'password' => bcrypt('password'),
    ]);

    AdminLog::create([
        'admin_id' => $admin->id,
        'action' => 'ban_user',
        'target_type' => 'user',
        'target_id' => '5',
        'details' => ['reason' => 'SevereHarassmentDetected'],
        'created_at' => now(),
    ]);

    AdminLog::create([
        'admin_id' => $admin->id,
        'action' => 'ban_user',
        'target_type' => 'user',
        'target_id' => '6',
        'details' => ['reason' => 'RoutineVerification'],
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.logs.index', [
        'search' => 'SevereHarassment',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Logs/Index')
        ->has('logs.data', 1)
        ->where('logs.data.0.target_id', 5)
    );
});

test('updating platform settings creates an admin audit log', function () {
    $admin = Admin::create([
        'name' => 'Setting Admin',
        'email' => 'admin-settings@test.local',
        'password' => bcrypt('password'),
    ]);

    $payload = [
        'rating_low_threshold' => 25,
        'order_auto_complete_delay' => 48,
        'order_dispute_window_minutes' => 120,
        'content_pack_price_min' => 150,
        'content_pack_price_max' => 5000,
        'moderate_new_packs' => true,
        'moderate_existing_packs' => false,
        'deposit_fee_percent' => 3.5,
        'withdrawal_fee_percent' => 2.5,
        'platform_fee_percent' => 12.0,
        'rating_deltas' => [
            'review_5star' => 1.0,
            'review_4star' => 0.5,
            'review_2star' => -0.5,
            'review_1star' => -1.5,
            'order_completed' => 0.3,
            'report_accepted' => -2.5,
            'review_dispute_approved' => 0.8,
        ],
    ];

    $response = $this->actingAs($admin, 'admin')
        ->from(route('admin.settings.index'))
        ->patch(route('admin.settings.update'), $payload);

    $response->assertSessionHas('success');

    $log = AdminLog::where('action', 'update_platform_settings')->first();
    expect($log)->not->toBeNull();
    expect($log->admin_id)->toBe($admin->id);
    expect($log->target_type)->toBe('platform_setting');
    expect((float) $log->details['platform_fee_percent'])->toBe(12.0);
});

test('user show endpoint returns wallet, transactions, admin logs and orders', function () {
    $admin = Admin::create([
        'name' => 'Admin Inspector',
        'email' => 'inspector@test.local',
        'password' => bcrypt('password'),
    ]);

    $user = User::factory()->create();
    $customer = User::factory()->create();

    // Create wallet and transaction (500 gross - 4% deposit fee = 480 net)
    $walletService = app(WalletService::class);
    $walletService->deposit($user, 500.0, 'Manual deposit test');

    // Create an order
    Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $user->id,
        'status' => 'paid',
    ]);

    // Create an admin audit log for this user
    AdminLogService::log(
        $admin->id,
        'cooldown_update',
        'user',
        $user->id,
        ['minutes' => 60]
    );

    $response = $this->actingAs($admin, 'admin')->get(route('admin.users.show', $user->id));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Users/Show')
        ->has('user.wallet')
        ->where('user.wallet.balance', 480)
        ->has('user.transactions', 1)
        ->has('user.admin_logs', 1)
        ->where('user.admin_logs.0.action', 'cooldown_update')
        ->has('user.recent_orders', 1)
    );
});
