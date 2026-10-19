<?php

use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->logPath = storage_path('logs/laravel.log');
    $this->originalLogContent = file_exists($this->logPath) ? file_get_contents($this->logPath) : null;
});

afterEach(function () {
    if ($this->originalLogContent !== null) {
        file_put_contents($this->logPath, $this->originalLogContent);
    } elseif (file_exists($this->logPath)) {
        unlink($this->logPath);
    }
});

test('guest cannot access admin system logs', function () {
    $response = $this->get(route('admin.system-logs.index'));
    $response->assertRedirect(route('admin.login'));
});

test('regular user cannot access admin system logs', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.system-logs.index'));
    $response->assertRedirect(route('admin.login'));
});

test('admin can view system logs and stats', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin-syslogs@test.local',
        'password' => bcrypt('password'),
    ]);

    $dummyLog = implode("\n", [
        '[2026-10-19 12:00:00] local.ERROR: Database connection timeout {"context":[]}',
        '[2026-10-19 12:05:00] local.WARNING: Deprecated function called {"context":[]}',
        '[2026-10-19 12:10:00] local.INFO: User registered successfully {"user_id":1}',
    ]);
    file_put_contents($this->logPath, $dummyLog);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.system-logs.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/SystemLogs/Index')
        ->has('logs.data', 3)
        ->where('stats.counts.total', 3)
        ->where('stats.counts.error', 1)
        ->where('stats.counts.warning', 1)
        ->where('stats.counts.info', 1)
    );
});

test('admin can filter system logs by level', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin-filter@test.local',
        'password' => bcrypt('password'),
    ]);

    $dummyLog = implode("\n", [
        '[2026-10-19 12:00:00] local.ERROR: Fatal crash occurred {"code":500}',
        '[2026-10-19 12:05:00] local.WARNING: Slow query detected {"duration":1200}',
        '[2026-10-19 12:10:00] local.INFO: Task scheduled {"task":"prune"}',
    ]);
    file_put_contents($this->logPath, $dummyLog);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.system-logs.index', ['level' => 'error']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/SystemLogs/Index')
        ->has('logs.data', 1)
        ->where('logs.data.0.level', 'ERROR')
        ->where('logs.data.0.message', 'Fatal crash occurred {"code":500}')
    );
});

test('admin can search system logs by keyword', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin-search@test.local',
        'password' => bcrypt('password'),
    ]);

    $dummyLog = implode("\n", [
        '[2026-10-19 12:00:00] local.ERROR: UniquePaymentGatewayFailedException occurred {"code":500}',
        '[2026-10-19 12:05:00] local.WARNING: Normal warning {"duration":1200}',
    ]);
    file_put_contents($this->logPath, $dummyLog);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.system-logs.index', [
        'search' => 'UniquePaymentGatewayFailed',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/SystemLogs/Index')
        ->has('logs.data', 1)
        ->where('logs.data.0.message', 'UniquePaymentGatewayFailedException occurred {"code":500}')
    );
});

test('admin can clear system logs and audit log is created', function () {
    $admin = Admin::create([
        'name' => 'Super Admin',
        'email' => 'admin-clear@test.local',
        'password' => bcrypt('password'),
    ]);

    file_put_contents($this->logPath, '[2026-10-19 12:00:00] local.ERROR: Sample error log to be cleared');
    expect(file_exists($this->logPath))->toBeTrue();
    expect(strlen(file_get_contents($this->logPath)))->toBeGreaterThan(0);

    $response = $this->actingAs($admin, 'admin')
        ->from(route('admin.system-logs.index'))
        ->delete(route('admin.system-logs.clear'));

    $response->assertRedirect(route('admin.system-logs.index'));
    $response->assertSessionHas('success');
    expect(file_get_contents($this->logPath))->toBe('');

    $logEntry = AdminLog::where('action', 'clear_system_logs')->first();
    expect($logEntry)->not->toBeNull();
    expect($logEntry->admin_id)->toBe($admin->id);
    expect($logEntry->target_type)->toBe('system_log');
});
