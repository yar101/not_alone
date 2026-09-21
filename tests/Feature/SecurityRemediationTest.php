<?php

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\AdminBroadcast;
use App\Models\ChatBlock;
use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\ContentPackPurchase;
use App\Models\Conversation;
use App\Models\IdolApplication;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\UserReport;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createTestAdmin(): Admin
{
    return Admin::create([
        'name' => 'Admin Test',
        'email' => 'admin_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

test('content pack cannot be purchased twice and does not double charge buyer', function () {
    config(['services.payments.mock_purchases' => false]);
    $walletService = app(WalletService::class);

    $seller = User::factory()->create(['is_idol' => true]);
    $buyer = User::factory()->create();

    $walletService->deposit($buyer, 1000.00, 'Initial deposit');

    $pack = ContentPack::create([
        'user_id' => $seller->id,
        'title' => 'Exclusive Pack',
        'description' => 'Test pack',
        'price' => 200,
        'status' => 'published',
    ]);

    // 1st purchase succeeds
    $purchase1 = $walletService->purchaseContentPack($buyer, $pack);
    expect($purchase1)->not->toBeNull();

    $buyerWallet = $walletService->getOrCreateWallet($buyer);
    expect((float) $buyerWallet->balance)->toEqual(760.00);

    // 2nd purchase throws DomainException and does NOT deduct funds
    try {
        $walletService->purchaseContentPack($buyer, $pack);
        $this->fail('Expected DomainException was not thrown');
    } catch (\DomainException $e) {
        expect($e->getMessage())->toContain('уже приобретён');
    }

    $buyerWallet->refresh();
    expect((float) $buyerWallet->balance)->toEqual(760.00);

    // Verify purchase count is exactly 1
    expect(ContentPackPurchase::where('content_pack_id', $pack->id)->where('user_id', $buyer->id)->count())->toBe(1);
});

test('completed order refund claws back payout from idol, reverses platform fee, and adjusts rating', function () {
    config(['services.payments.mock_purchases' => false]);
    $walletService = app(WalletService::class);
    $orderService = app(OrderService::class);

    $idol = User::factory()->create(['is_idol' => true, 'rating' => 50.0]);
    $customer = User::factory()->create();

    $walletService->deposit($customer, 1000.00, 'Initial deposit');

    $category = ServiceCategory::create(['name' => 'Разговоры_'.uniqid()]);
    $unit = ServiceTimeUnit::create(['name' => '30 мин_'.uniqid()]);
    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $unit->id,
        'name' => 'Аудиочат',
        'price' => 500,
        'is_active' => true,
        'status' => 'approved',
    ]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'service_id' => $service->id,
        'price' => 500,
        'quantity' => 1,
    ]);

    // Pay order -> hold customer funds (500)
    $orderService->pay($order, $customer);

    $custWallet = $walletService->getOrCreateWallet($customer);
    expect((float) $custWallet->balance)->toEqual(460.00);
    expect((float) $custWallet->held_balance)->toEqual(500.00);

    // Complete order (releases customer hold, holds idol net payout during dispute window)
    $orderService->confirmCompletion($order, $customer);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed);

    // Release payout to idol available balance
    $walletService->releaseIdolPayout($order);

    $idolWallet = $walletService->getOrCreateWallet($idol);
    expect((float) $idolWallet->balance)->toEqual(450.00); // 500 gross - 50 platform fee
    $custWallet->refresh();
    expect((float) $custWallet->held_balance)->toEqual(0.00);

    $admin = createTestAdmin();

    // Admin transitions completed order to Refunded (e.g. dispute approved)
    $orderService->adminTransition($order, OrderStatus::Refunded, $admin->id, 'Dispute approved by admin');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Refunded);

    // Idol balance clawed back by net amount (450) -> returns to 0.00
    $idolWallet->refresh();
    expect((float) $idolWallet->balance)->toEqual(0.00);

    // Customer receives full 500.00 refund back to available balance (460 + 500 = 960)
    $custWallet->refresh();
    expect((float) $custWallet->balance)->toEqual(960.00);

    // Verify OrderClawback transaction exists on idol wallet
    $clawbackTx = WalletTransaction::where('wallet_id', $idolWallet->id)
        ->where('type', WalletTransactionType::OrderClawback)
        ->first();
    expect($clawbackTx)->not->toBeNull();
    expect((float) $clawbackTx->amount)->toEqual(-450.00);
});

test('support chat controller rejects requests on non-support conversations with 404', function () {
    $admin = createTestAdmin();
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Private direct conversation between users (is_support = false)
    $conversation = Conversation::create(['is_support' => false]);
    $conversation->participants()->create(['user_id' => $userA->id]);
    $conversation->participants()->create(['user_id' => $userB->id]);

    $this->actingAs($admin, 'admin')
        ->getJson("/admin/support/{$conversation->id}/messages")
        ->assertStatus(404);

    $this->actingAs($admin, 'admin')
        ->postJson("/admin/support/{$conversation->id}/send", ['body' => 'Admin trying to intercept'])
        ->assertStatus(404);

    $this->actingAs($admin, 'admin')
        ->postJson("/admin/support/{$conversation->id}/close")
        ->assertStatus(404);
});

test('conversation upload is blocked for blocked user and completed order chats', function () {
    Storage::fake('public');
    Event::fake();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conversation = Conversation::create();
    $conversation->participants()->create(['user_id' => $userA->id]);
    $conversation->participants()->create(['user_id' => $userB->id]);

    // User A blocks User B
    ChatBlock::create([
        'blocker_id' => $userA->id,
        'blocked_id' => $userB->id,
        'reason' => 'Spam',
    ]);

    $file = UploadedFile::fake()->image('test.jpg');

    // Blocked User B attempts to upload a file -> 403
    $this->actingAs($userB)
        ->postJson(route('conversations.upload', $conversation), ['file' => $file])
        ->assertStatus(403);

    // Remove block
    ChatBlock::where('blocker_id', $userA->id)->delete();

    // Associate conversation with a completed order
    $order = Order::factory()->create([
        'customer_id' => $userA->id,
        'idol_id' => $userB->id,
        'status' => OrderStatus::Completed,
        'conversation_id' => $conversation->id,
    ]);
    $conversation->update(['order_id' => $order->id]);

    // Upload in completed order chat -> 422
    $this->actingAs($userA)
        ->postJson(route('conversations.upload', $conversation), ['file' => $file])
        ->assertStatus(422);
});

test('admin broadcast validates target_filters keys and scopeFiltered safely ignores malformed casts', function () {
    $admin = createTestAdmin();

    // Validation fails if age_from is not an integer
    $response = $this->actingAs($admin, 'admin')
        ->post(route('admin.messages.store'), [
            'title' => ['ru' => 'Тест', 'en' => 'Test'],
            'body' => ['ru' => 'Тест', 'en' => 'Test'],
            'target' => 'filtered',
            'target_filters' => [
                'age_from' => 'not-an-int',
            ],
        ]);
    $response->assertSessionHasErrors('target_filters.age_from');

    // Even if a broadcast in DB had invalid age_from directly, scopeFiltered does not throw Postgres error
    $broadcast = AdminBroadcast::create([
        'admin_id' => $admin->id,
        'title' => ['ru' => 'Тест', 'en' => 'Test'],
        'body' => ['ru' => 'Тест', 'en' => 'Test'],
        'target' => 'filtered',
        'target_filters' => [
            'age_from' => 'malformed_value',
            'registered_from' => 'invalid_date',
        ],
    ]);

    $user = User::factory()->create(['birth_date' => now()->subYears(25)]);

    // Querying forUser does not throw SQL syntax error
    $results = AdminBroadcast::forUser($user)->get();
    expect($results)->not->toBeNull();
});

test('report controller review is idempotent and does not repeatedly adjust idol rating', function () {
    $admin = createTestAdmin();
    $reporter = User::factory()->create();
    $reportedIdol = User::factory()->create(['is_idol' => true, 'rating' => 50.0]);

    $report = UserReport::create([
        'reporter_id' => $reporter->id,
        'reported_id' => $reportedIdol->id,
        'reason' => 'inappropriate',
        'status' => 'pending',
    ]);

    // 1st review succeeds and deducts rating (-2.0)
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.reports.review', $report), ['admin_note' => 'Violation confirmed'])
        ->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe('reviewed');

    $reportedIdol->refresh();
    $firstRating = (float) $reportedIdol->rating;
    expect($firstRating)->toEqual(48.0);

    // 2nd review returns error and does NOT deduct rating again
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.reports.review', $report), ['admin_note' => 'Violation confirmed again'])
        ->assertSessionHasErrors('report');

    $reportedIdol->refresh();
    expect((float) $reportedIdol->rating)->toEqual(48.0);
});

test('export controller sanitizes formula injection characters in CSV export', function () {
    $admin = createTestAdmin();

    User::factory()->create([
        'name' => '=cmd|"/C calc"!A0',
        'email' => '@evil.com',
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.export.users'));

    $response->assertOk();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    // Must be sanitized with leading single quote
    expect($content)->toContain("'=cmd|")
        ->and($content)->toContain("'@evil.com");
});

test('admin dashboard and applications handle soft-deleted users without HTTP 500', function () {
    $admin = createTestAdmin();
    $user = User::factory()->create();

    $application = IdolApplication::create([
        'user_id' => $user->id,
        'face_photo_path' => 'applications/test.jpg',
        'status' => 'pending',
    ]);

    // Soft delete the user
    $user->delete();

    // Dashboard renders without error
    $dashResponse = $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'));
    $dashResponse->assertOk();

    // Applications index renders without error
    $appResponse = $this->actingAs($admin, 'admin')
        ->get(route('admin.applications.index'));
    $appResponse->assertOk();

    // Applications show renders without error
    $showResponse = $this->actingAs($admin, 'admin')
        ->get(route('admin.applications.show', $application));
    $showResponse->assertOk();
});

test('content pack with existing purchases is soft deleted and files are preserved', function () {
    Storage::fake('public');

    $idol = User::factory()->create(['is_idol' => true]);
    $buyer = User::factory()->create();

    $pack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Special Pack',
        'price' => 300,
        'status' => 'has_remarks', // Changed status from published
    ]);

    $photo = ContentPackPhoto::create([
        'content_pack_id' => $pack->id,
        'path' => 'content-packs/'.$pack->id.'/photo1.jpg',
        'sort_order' => 1,
    ]);
    Storage::put($photo->path, 'fake photo content');

    ContentPackPurchase::create([
        'content_pack_id' => $pack->id,
        'user_id' => $buyer->id,
        'price_paid' => 300,
        'purchased_at' => now(),
    ]);

    $this->actingAs($idol)
        ->delete(route('content-packs.destroy', $pack))
        ->assertRedirect();

    // Pack is soft deleted (not force deleted)
    expect(ContentPack::withTrashed()->find($pack->id))->not->toBeNull();
    expect(ContentPack::find($pack->id))->toBeNull();

    // Purchase record is intact
    expect(ContentPackPurchase::where('content_pack_id', $pack->id)->count())->toBe(1);

    // Photo file still exists in storage
    Storage::assertExists($photo->path);
});

test('markBroadcastRead returns 404 cleanly when broadcast does not exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/notifications/broadcast/99999999/read')
        ->assertStatus(404);
});

test('registration route has throttle middleware configured', function () {
    $postRoute = collect(Route::getRoutes()->get('POST'))
        ->first(fn ($r) => $r->uri() === 'register');

    expect($postRoute)->not->toBeNull();
    $middleware = $postRoute->gatherMiddleware();
    expect($middleware)->toContain('throttle:10,1');
});
