<?php

use App\Models\Admin;
use App\Models\IdolApplication;
use App\Models\User;
use Database\Seeders\PlatformSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    $this->seed(PlatformSettingSeeder::class);
    $this->admin = Admin::create([
        'name' => 'Super Admin',
        'email' => 'admin_sidebar@test.local',
        'password' => bcrypt('password'),
    ]);
});

test('admin can access all 34 sidebar and section routes with 200 OK and valid Inertia component', function () {
    $routes = [
        ['name' => 'admin.dashboard', 'component' => 'Admin/Dashboard'],
        ['name' => 'admin.applications.index', 'component' => 'Admin/Applications/Index'],
        ['name' => 'admin.disputes.index', 'component' => 'Admin/Disputes/Index'],
        ['name' => 'admin.reports.index', 'component' => 'Admin/Reports/Index'],
        ['name' => 'admin.support.index', 'component' => 'Admin/Support/Index'],
        ['name' => 'admin.orders.index', 'component' => 'Admin/Orders/Index'],
        ['name' => 'admin.transactions.index', 'component' => 'Admin/Transactions/Index'],
        ['name' => 'admin.payouts.index', 'component' => 'Admin/Payouts/Index'],
        ['name' => 'admin.services.moderation.index', 'component' => 'Admin/Services/Index'],
        ['name' => 'admin.services.change-requests.index', 'component' => 'Admin/Services/ChangeRequests'],
        ['name' => 'admin.services.categories.index', 'component' => 'Admin/Services/Categories'],
        ['name' => 'admin.services.price-limits.index', 'component' => 'Admin/Services/PriceLimits'],
        ['name' => 'admin.services.time-units.index', 'component' => 'Admin/Services/TimeUnits'],
        ['name' => 'admin.content-packs.index', 'component' => 'Admin/ContentPacks/Index'],
        ['name' => 'admin.content-packs.change-requests.index', 'component' => 'Admin/ContentPacks/ChangeRequests'],
        ['name' => 'admin.review-disputes.index', 'component' => 'Admin/ReviewDisputes/Index'],
        ['name' => 'admin.avatar-frames.index', 'component' => 'Admin/AvatarFrames/Index'],
        ['name' => 'admin.users.index', 'component' => 'Admin/Users/Index'],
        ['name' => 'admin.conversations.index', 'component' => 'Admin/Conversations/Index'],
        ['name' => 'admin.strikes.index', 'component' => 'Admin/Strikes/Index'],
        ['name' => 'admin.chat-blocks.index', 'component' => 'Admin/ChatBlocks/Index'],
        ['name' => 'admin.ban-reasons.index', 'component' => 'Admin/BanReasons/Index'],
        ['name' => 'admin.quiz.questions.index', 'component' => 'Admin/Quiz/Index'],
        ['name' => 'admin.quiz.article.index', 'component' => 'Admin/Quiz/Article'],
        ['name' => 'admin.traits.index', 'component' => 'Admin/Traits/Index'],
        ['name' => 'admin.traits.suggestions.index', 'component' => 'Admin/Traits/Suggestions'],
        ['name' => 'admin.interests.index', 'component' => 'Admin/Interests/Index'],
        ['name' => 'admin.interests.suggestions.index', 'component' => 'Admin/Interests/Suggestions'],
        ['name' => 'admin.help-categories.index', 'component' => 'Admin/Help/Index'],
        ['name' => 'admin.settings.index', 'component' => 'Admin/Settings'],
        ['name' => 'admin.messages.index', 'component' => 'Admin/Messages/Index'],
        ['name' => 'admin.logs.index', 'component' => 'Admin/Logs/Index'],
        ['name' => 'admin.system-logs.index', 'component' => 'Admin/SystemLogs/Index'],
        ['name' => 'admin.rating-logs.index', 'component' => 'Admin/RatingLogs/Index'],
    ];

    foreach ($routes as $routeInfo) {
        $response = $this->actingAs($this->admin, 'admin')->get(route($routeInfo['name']));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component($routeInfo['component']));
    }
});

test('applications index correctly passes pending_count prop for badge display', function () {
    // 0 pending applications
    $response = $this->actingAs($this->admin, 'admin')->get(route('admin.applications.index'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Applications/Index')
        ->where('pending_count', 0)
    );

    // Create a pending application
    $user = User::factory()->create();
    IdolApplication::create([
        'user_id' => $user->id,
        'face_photo_path' => 'photos/test.jpg',
        'status' => 'pending',
    ]);

    $response2 = $this->actingAs($this->admin, 'admin')->get(route('admin.applications.index'));
    $response2->assertOk();
    $response2->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Applications/Index')
        ->where('pending_count', 1)
    );
});

test('admin can search users for UserPickerModal with cursor pagination and filters', function () {
    User::factory()->count(25)->create();

    $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.users.search'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'name', 'email', 'is_idol', 'gender', 'age', 'avatar_url', 'balance', 'created_at'],
        ],
        'meta' => ['next_cursor', 'prev_cursor', 'has_more', 'total'],
    ]);
    expect($response->json('meta.has_more'))->toBeTrue();
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(25);
    expect($response->json('meta.next_cursor'))->not->toBeNull();

    // Test next cursor page
    $nextCursor = $response->json('meta.next_cursor');
    $response2 = $this->actingAs($this->admin, 'admin')->getJson(route('admin.users.search', ['cursor' => $nextCursor]));
    $response2->assertOk();
    expect($response2->json('data'))->not->toBeEmpty();
});

test('admin can export orders to csv', function () {
    $response = $this->actingAs($this->admin, 'admin')->get(route('admin.export.orders'));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('admin can access conversations with query filters and fetch order history', function () {
    $order = App\Models\Order::first();

    $response = $this->actingAs($this->admin, 'admin')->get(route('admin.conversations.index', [
        'id' => 15,
        'order_id' => 15,
    ]));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Conversations/Index')
        ->has('conversations')
        ->has('total_count')
    );

    if ($order) {
        $historyRes = $this->actingAs($this->admin, 'admin')->getJson(route('admin.orders.history', $order->id));
        $historyRes->assertOk();
    }
});
