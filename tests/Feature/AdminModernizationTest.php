<?php

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\ContentPack;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Models\UserStrike;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    $this->admin = Admin::create([
        'name' => 'Super Admin',
        'email' => 'superadmin@test.local',
        'password' => bcrypt('password'),
    ]);
});

test('admin can view order show page with details, customer, idol, transactions and status history', function () {
    $customer = User::factory()->create(['name' => 'Customer User']);
    $idol = User::factory()->create(['name' => 'Idol User', 'is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Pending,
    ]);

    $order->logStatusChange(null, 'pending', 'system', 0, 'Заказ создан');

    $response = $this->actingAs($this->admin, 'admin')->get(route('admin.orders.show', $order));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Orders/Show')
        ->has('order', fn (Assert $o) => $o
            ->where('id', $order->id)
            ->where('status', 'pending')
            ->has('customer')
            ->has('idol')
            ->has('history')
            ->etc()
        )
    );
});

test('admin can destroy/revoke strike, returning rating to idol and logging action', function () {
    $idol = User::factory()->create(['is_idol' => true, 'rating' => 4.0]);

    $strike = UserStrike::create([
        'user_id' => $idol->id,
        'admin_id' => $this->admin->id,
        'rating_deducted' => -1.0,
        'admin_note' => 'Тестовое нарушение правил',
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->actingAs($this->admin, 'admin')->delete(route('admin.strikes.destroy', $strike));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(UserStrike::find($strike->id))->toBeNull();
    expect((float) $idol->fresh()->rating)->toBe(5.0);

    $log = AdminLog::where('action', 'strike_cancel')->where('target_id', $strike->id)->first();
    expect($log)->not->toBeNull();
    expect($log->details['user_id'])->toBe($idol->id);
});

test('admin can impersonate user and leave back to admin panel', function () {
    $user = User::factory()->create(['name' => 'Alice Target']);

    // 1. Impersonate
    $response = $this->actingAs($this->admin, 'admin')->post(route('admin.users.impersonate', $user));

    $response->assertRedirect('/');
    expect(auth('web')->id())->toBe($user->id);
    expect(session('impersonator_admin_id'))->toBe($this->admin->id);

    // 2. Leave impersonation
    $leaveResponse = $this->withSession(['impersonator_admin_id' => $this->admin->id])
        ->actingAs($user, 'web')
        ->post(route('impersonate.leave'));

    $leaveResponse->assertRedirect(route('admin.users.show', $user->id));
    expect(session()->has('impersonator_admin_id'))->toBeFalse();
});

test('admin quick search returns users, orders, and content packs matching query', function () {
    $user = User::factory()->create(['name' => 'TargetSearchUser', 'email' => 'targetsearch@example.com']);
    $idol = User::factory()->create(['name' => 'TargetSearchIdol', 'is_idol' => true]);

    $order = Order::create([
        'customer_id' => $user->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
    ]);

    $pack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'TargetSearch Pack Title',
        'description' => 'Test pack description',
        'price' => 500,
        'status' => 'pending_review',
    ]);

    $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.quick-search', ['q' => 'TargetSearch']));

    $response->assertOk();
    $data = $response->json();

    expect(count($data['users']))->toBeGreaterThanOrEqual(2);
    expect(count($data['packs']))->toBeGreaterThanOrEqual(1);

    // Search specifically by order ID
    $orderSearchResponse = $this->actingAs($this->admin, 'admin')->getJson(route('admin.quick-search', ['q' => (string) $order->id]));
    $orderSearchResponse->assertOk();
    $orderData = $orderSearchResponse->json();
    expect(count($orderData['orders']))->toBeGreaterThanOrEqual(1);
    expect($orderData['orders'][0]['id'])->toBe($order->id);
});

test('admin can bulk approve and bulk reject content packs', function () {
    $idol = User::factory()->create(['is_idol' => true]);

    $pack1 = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Pack 1 for bulk approve',
        'description' => 'Desc 1',
        'price' => 300,
        'status' => 'pending_review',
    ]);

    $pack2 = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Pack 2 for bulk approve',
        'description' => 'Desc 2',
        'price' => 400,
        'status' => 'pending_review',
    ]);

    $pack3 = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Pack 3 for bulk reject',
        'description' => 'Desc 3',
        'price' => 500,
        'status' => 'pending_review',
    ]);

    // Bulk approve 1 and 2
    $approveResp = $this->actingAs($this->admin, 'admin')->post(route('admin.content-packs.bulk-approve'), [
        'ids' => [$pack1->id, $pack2->id],
    ]);

    $approveResp->assertSessionHas('success');
    expect($pack1->fresh()->status)->toBe('approved');
    expect($pack2->fresh()->status)->toBe('approved');

    // Bulk reject 3
    $rejectResp = $this->actingAs($this->admin, 'admin')->post(route('admin.content-packs.bulk-reject'), [
        'ids' => [$pack3->id],
    ]);

    $rejectResp->assertSessionHas('success');
    expect($pack3->fresh()->status)->toBe('rejected');
});

test('admin can export orders, transactions, and logs to CSV', function () {
    $user = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    Order::create([
        'customer_id' => $user->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
    ]);

    AdminLog::create([
        'admin_id' => $this->admin->id,
        'action' => 'test_export_action',
        'target_type' => 'user',
        'target_id' => $user->id,
        'created_at' => now(),
    ]);

    // Orders export
    $ordersExport = $this->actingAs($this->admin, 'admin')->get(route('admin.export.orders'));
    $ordersExport->assertOk();
    $ordersExport->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    // Transactions export
    $txExport = $this->actingAs($this->admin, 'admin')->get(route('admin.export.transactions'));
    $txExport->assertOk();
    $txExport->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    // Logs export
    $logsExport = $this->actingAs($this->admin, 'admin')->get(route('admin.export.logs'));
    $logsExport->assertOk();
    $logsExport->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('admin can filter conversations by order_id and fetch messages', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $conversation = Conversation::create(['order_id' => null]);
    $conversation->users()->attach([$customer->id, $idol->id]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'conversation_id' => $conversation->id,
        'status' => OrderStatus::Pending,
    ]);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $customer->id,
        'body' => 'Hello idol, testing communication link',
    ]);

    // Check order show returns the conversation_id
    $showResp = $this->actingAs($this->admin, 'admin')->get(route('admin.orders.show', $order));
    $showResp->assertOk();
    $showResp->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Orders/Show')
        ->where('order.conversation_id', $conversation->id)
    );

    // Check conversation index by order_id filters to this conversation
    $convResp = $this->actingAs($this->admin, 'admin')->get(route('admin.conversations.index', ['order_id' => $order->id]));
    $convResp->assertOk();
    $convResp->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Conversations/Index')
        ->has('conversations.data', 1)
        ->where('conversations.data.0.id', $conversation->id)
    );

    // Check messages endpoint
    $messagesResp = $this->actingAs($this->admin, 'admin')->getJson(route('admin.conversations.messages', $conversation->id));
    $messagesResp->assertOk();
    expect($messagesResp->json('messages.0.body'))->toBe('Hello idol, testing communication link');
});

test('admin quick search supports single digit query for ID lookups', function () {
    $user = User::factory()->create(['name' => 'Single Digit Test User']);
    $order = Order::create([
        'customer_id' => $user->id,
        'idol_id' => $user->id,
        'status' => OrderStatus::Pending,
    ]);

    $searchResp = $this->actingAs($this->admin, 'admin')->getJson(route('admin.quick-search', ['q' => (string) $order->id]));
    $searchResp->assertOk();
    $data = $searchResp->json();
    expect(count($data['orders']))->toBeGreaterThanOrEqual(1);
    expect($data['orders'][0]['id'])->toBe($order->id);
});

test('admin.transactions.show redirects Inertia requests to transactions.index search', function () {
    $user = User::factory()->create();
    $wallet = $user->wallet ?? \App\Models\Wallet::create(['user_id' => $user->id, 'balance' => 1000]);

    $tx = WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'type' => \App\Enums\WalletTransactionType::Deposit,
        'amount' => 500,
        'balance_before' => 500,
        'balance_after' => 1000,
        'status' => \App\Enums\WalletTransactionStatus::Completed,
        'description' => 'Test deposit transaction',
    ]);

    $inertiaResp = $this->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class)
        ->actingAs($this->admin, 'admin')
        ->withHeaders(['X-Inertia' => 'true'])
        ->get(route('admin.transactions.show', $tx));

    $inertiaResp->assertRedirect(route('admin.transactions.index', ['search' => $tx->id]));
});

test('admin.transactions.show returns JSON for API requests', function () {
    $user = User::factory()->create();
    $wallet = $user->wallet ?? \App\Models\Wallet::create(['user_id' => $user->id, 'balance' => 1000]);

    $tx = WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'type' => \App\Enums\WalletTransactionType::Deposit,
        'amount' => 500,
        'balance_before' => 500,
        'balance_after' => 1000,
        'status' => \App\Enums\WalletTransactionStatus::Completed,
        'description' => 'Test deposit transaction',
    ]);

    $apiResp = $this->actingAs($this->admin, 'admin')
        ->getJson(route('admin.transactions.show', $tx));

    $apiResp->assertOk();
    expect($apiResp->json('id'))->toBe($tx->id);
    expect((float) $apiResp->json('amount'))->toBe(500.0);
    expect($apiResp->json('status'))->toBe('completed');
});

test('HandleInertiaRequests generates flash token and suppresses flash on partial reloads', function () {
    $sessionStore = session()->driver();
    $sessionStore->flash('success', 'Тестовое уведомление');

    $request = \Illuminate\Http\Request::create('/admin/dashboard', 'GET');
    $request->setLaravelSession($sessionStore);

    $middleware = new \App\Http\Middleware\HandleInertiaRequests();
    $shared = $middleware->share($request);

    $flashFn = $shared['flash'];
    $flashData = is_callable($flashFn) ? $flashFn() : $flashFn;

    expect($flashData['success'])->toBe('Тестовое уведомление');
    expect($flashData['token'])->not->toBeNull();
    expect(strlen($flashData['token']))->toBeGreaterThanOrEqual(10);

    // Now simulate a partial reload request (like Inertia deferred props)
    $partialRequest = \Illuminate\Http\Request::create('/admin/dashboard', 'GET');
    $partialRequest->headers->set('X-Inertia', 'true');
    $partialRequest->headers->set('X-Inertia-Partial-Data', 'pending_applications_count');
    $partialRequest->headers->set('X-Inertia-Partial-Component', 'Admin/Dashboard');
    $partialRequest->setLaravelSession($sessionStore);

    $partialShared = $middleware->share($partialRequest);
    $partialFlashFn = $partialShared['flash'];
    $partialFlashData = is_callable($partialFlashFn) ? $partialFlashFn() : $partialFlashFn;

    expect($partialFlashData['token'])->toBeNull();
    expect($partialFlashData['success'])->toBeNull();
});

test('admin can clear system logs and receives success flash', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.system-logs.clear'));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Системный лог успешно очищен.');
});


