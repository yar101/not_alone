<?php

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\ContentPack;
use App\Models\ContentPackChangeRequest;
use App\Models\ContentPackPurchase;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\Review;
use App\Models\ReviewDispute;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceChangeRequest;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\UserProfileService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createAuditTestAdmin(): Admin
{
    return Admin::create([
        'name' => 'Audit Admin',
        'email' => 'audit_admin_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

test('wallet routes are accessible via wallet.show and alias wallet.index redirects to wallet', function () {
    $user = User::factory()->create();

    // Direct access to wallet.show
    $responseShow = $this->actingAs($user)->get(route('wallet.show'));
    $responseShow->assertOk();

    // Access via alias wallet.index
    $responseIndex = $this->actingAs($user)->get(route('wallet.index'));
    $responseIndex->assertRedirect('/wallet');
});

test('payments mock_purchases is securely disabled in production environment', function () {
    $user = User::factory()->create();
    $wallet = $user->getOrCreateWallet();

    // Mock production environment
    app()->detectEnvironment(fn () => 'production');
    config(['services.payments.mock_purchases' => false]);

    $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->actingAs($user)
        ->postJson(route('wallet.deposit'), [
            'amount' => 1000,
        ]);

    $response->assertStatus(403);
    expect($response->json('error'))->toContain('Прямое пополнение доступно только в тестовом режиме');
    expect((float) $wallet->fresh()->balance)->toBe(0.00);
});

test('payments mock_purchases works in local or testing environments', function () {
    $user = User::factory()->create();
    $wallet = $user->getOrCreateWallet();

    $response = $this->actingAs($user)->postJson(route('wallet.deposit'), [
        'amount' => 1500,
    ]);

    $response->assertOk();
    // 1500 - 4% deposit fee = 1440.00
    expect((float) $wallet->fresh()->balance)->toBe(1440.00);
});

test('service and content pack change request show and decide handle empty or null changed_fields without errors', function () {
    $admin = createAuditTestAdmin();
    $idol = User::factory()->create(['is_idol' => true]);

    $category = ServiceCategory::first() ?? ServiceCategory::create(['name' => ['ru' => 'Test Cat', 'en' => 'Test Cat']]);
    $timeUnit = ServiceTimeUnit::first() ?? ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour']]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Test Service', 'en' => 'Test Service'],
        'price' => 1000,
        'status' => 'approved',
        'is_active' => true,
    ]);

    // ServiceChangeRequest with empty changed_fields
    $serviceCr = ServiceChangeRequest::create([
        'service_id' => $service->id,
        'changed_fields' => [],
        'status' => 'pending',
    ]);

    $responseShow = $this->actingAs($admin, 'admin')->get(route('admin.services.change-requests.show', $serviceCr));
    $responseShow->assertOk();

    $responseDecide = $this->actingAs($admin, 'admin')->post(route('admin.services.change-requests.decide', $serviceCr), [
        'decision' => 'rejected',
        'admin_comment' => 'Отклонено',
    ]);
    $responseDecide->assertRedirect();
    expect($serviceCr->fresh()->status)->toBe('rejected');

    // ContentPackChangeRequest with empty changed_fields
    $pack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Test Pack',
        'description' => 'Test Description',
        'price' => 500,
        'status' => 'approved',
    ]);

    $packCr = ContentPackChangeRequest::create([
        'content_pack_id' => $pack->id,
        'changed_fields' => [],
        'status' => 'pending',
    ]);

    $responsePackShow = $this->actingAs($admin, 'admin')->get(route('admin.content-packs.change-requests.show', $packCr));
    $responsePackShow->assertOk();

    $responsePackDecide = $this->actingAs($admin, 'admin')->post(route('admin.content-packs.change-requests.decide', $packCr), [
        'decision' => 'rejected',
        'admin_comment' => 'Отклонено',
    ]);
    $responsePackDecide->assertRedirect();
    expect($packCr->fresh()->status)->toBe('rejected');
});

test('concurrent review creation enforces uniqueness and returns 422 without SQL errors', function () {
    Event::fake();

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true, 'rating' => 50.00]);

    $order1 = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
        'completed_at' => now(),
    ]);

    $order2 = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
        'completed_at' => now(),
    ]);

    // First review succeeds
    $res1 = $this->actingAs($customer)->postJson(route('orders.review.store', $order1), [
        'rating' => 5,
        'text' => 'Первый отзыв',
    ]);
    $res1->assertOk();

    // Second review for the same idol returns clean 422 already_reviewed
    $res2 = $this->actingAs($customer)->postJson(route('orders.review.store', $order2), [
        'rating' => 4,
        'text' => 'Второй отзыв',
    ]);
    $res2->assertStatus(422);
    expect($res2->json('message'))->toBe('already_reviewed');

    // Only one review exists for this reviewer and idol
    expect(Review::where('reviewer_id', $customer->id)->where('idol_id', $idol->id)->count())->toBe(1);
});

test('soft-deleted users in review disputes and order disputes correctly have is_deleted marked as true', function () {
    $admin = createAuditTestAdmin();
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $review = Review::create([
        'order_id' => null,
        'reviewer_id' => $customer->id,
        'idol_id' => $idol->id,
        'rating' => 1,
        'text' => 'Тестовый отзыв',
    ]);

    $reviewDispute = ReviewDispute::create([
        'review_id' => $review->id,
        'idol_id' => $idol->id,
        'reason' => 'Ложный отзыв',
        'status' => 'pending',
    ]);

    // Soft-delete the reviewer
    $customer->delete();

    $responseReviewDispute = $this->actingAs($admin, 'admin')->get(route('admin.review-disputes.index', ['status' => 'all']));
    $responseReviewDispute->assertOk();
    $responseReviewDispute->assertInertia(function (Assert $page) use ($customer) {
        $page->component('Admin/ReviewDisputes/Index')
            ->has('disputes.data', 1, function (Assert $item) use ($customer) {
                $item->where('review.reviewer.id', $customer->id)
                    ->where('review.reviewer.is_deleted', true)
                    ->etc();
            });
    });

    // Order dispute with soft-deleted customer
    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Disputed,
    ]);

    $orderDispute = OrderDispute::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'reason' => 'Спор по заказу',
        'details' => 'Подробности',
        'status' => 'open',
    ]);

    $responseOrderDispute = $this->actingAs($admin, 'admin')->get(route('admin.disputes.index', ['status' => 'all']));
    $responseOrderDispute->assertOk();
    $responseOrderDispute->assertInertia(function (Assert $page) use ($customer) {
        $page->component('Admin/Disputes/Index')
            ->has('disputes.data', 1, function (Assert $item) use ($customer) {
                $item->where('customer.id', $customer->id)
                    ->where('customer.is_deleted', true)
                    ->etc();
            });
    });
});

test('content pack purchases remain associated via withTrashed when content pack is soft deleted', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $buyer = User::factory()->create();

    $pack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Exclusive Pack',
        'description' => 'Test pack description',
        'price' => 100,
        'status' => 'approved',
    ]);

    $purchase = ContentPackPurchase::create([
        'content_pack_id' => $pack->id,
        'user_id' => $buyer->id,
        'price_paid' => 100,
        'purchased_at' => now(),
    ]);

    // Soft delete the content pack
    $pack->delete();
    expect($pack->trashed())->toBeTrue();

    // Query purchase through relation
    $freshPurchase = ContentPackPurchase::where('id', $purchase->id)->first();
    expect($freshPurchase->contentPack)->not->toBeNull();
    expect($freshPurchase->contentPack->id)->toBe($pack->id);
    expect($freshPurchase->contentPack->title)->toBe('Exclusive Pack');
});

test('admin date filtering uses startOfDay and endOfDay boundary ranges correctly', function () {
    $admin = createAuditTestAdmin();
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $targetDate = Carbon::parse('2026-05-15');

    // Order created at beginning of target day
    $orderMorning = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
    ]);
    $orderMorning->created_at = $targetDate->copy()->startOfDay()->addSecond();
    $orderMorning->save();

    // Order created at end of target day
    $orderNight = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
    ]);
    $orderNight->created_at = $targetDate->copy()->endOfDay()->subSecond();
    $orderNight->save();

    // Order created on another day
    $orderOther = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
    ]);
    $orderOther->created_at = $targetDate->copy()->addDays(2);
    $orderOther->save();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.orders.index', [
        'date_from' => '2026-05-15',
        'date_to' => '2026-05-15',
    ]));

    $response->assertOk();
    $response->assertInertia(function (Assert $page) use ($orderMorning, $orderNight, $orderOther) {
        $page->component('Admin/Orders/Index')
            ->has('orders.data', 2)
            ->where('orders.data.0.id', $orderNight->id)
            ->where('orders.data.1.id', $orderMorning->id);
    });
});

test('user profile service pagination correctly counts idols without groupBy pagination skew', function () {
    $viewer = User::factory()->create();
    $category = ServiceCategory::first() ?? ServiceCategory::create(['name' => ['ru' => 'Music', 'en' => 'Music']]);
    $timeUnit = ServiceTimeUnit::first() ?? ServiceTimeUnit::create(['name' => ['ru' => 'hour', 'en' => 'hour']]);

    // Create 3 distinct idols each having 2 services in this category
    for ($i = 0; $i < 3; $i++) {
        $idol = User::factory()->create(['is_idol' => true]);
        Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => ['ru' => "Service A {$i}"],
            'price' => 100,
            'status' => 'approved',
            'is_active' => true,
        ]);
        Service::create([
            'user_id' => $idol->id,
            'category_id' => $category->id,
            'time_unit_id' => $timeUnit->id,
            'name' => ['ru' => "Service B {$i}"],
            'price' => 200,
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    $service = app(UserProfileService::class);
    $request = Request::create('/', 'GET', ['page' => 1, 'per_page' => 10]);
    $session = app('session.store');
    $request->setLaravelSession($session);

    $result = $service->getCategoryIdols($viewer, $category, $request);

    // Total must equal 3 (distinct idols), NOT 6 (total services count)
    expect($result['total'])->toBe(3);
    expect(count($result['idols']))->toBe(3);
});

test('bulk moderation actions record service reviews in database', function () {
    $admin = createAuditTestAdmin();
    $idol = User::factory()->create(['is_idol' => true]);

    $category = ServiceCategory::first() ?? ServiceCategory::create(['name' => ['ru' => 'Art', 'en' => 'Art']]);
    $timeUnit = ServiceTimeUnit::first() ?? ServiceTimeUnit::create(['name' => ['ru' => 'pc', 'en' => 'pc']]);

    $service1 = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Bulk Service 1'],
        'price' => 500,
        'status' => 'pending',
    ]);

    $service2 = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Bulk Service 2'],
        'price' => 600,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.services.moderation.bulk-approve'), [
        'ids' => [$service1->id, $service2->id],
    ]);
    $response->assertRedirect();

    expect($service1->fresh()->status)->toBe('approved');
    expect($service2->fresh()->status)->toBe('approved');

    // Verify service reviews history was generated
    expect($service1->reviews()->where('decision', 'approved')->exists())->toBeTrue();
    expect($service2->reviews()->where('decision', 'approved')->exists())->toBeTrue();
});

test('database schema contains all newly added composite and polymorphic indexes', function () {
    $expectedIndexes = [
        'users' => ['users_is_banned_rating_index'],
        'content_packs' => ['content_packs_status_created_at_index'],
        'idol_applications' => ['idol_applications_status_created_at_index'],
        'order_status_history' => ['order_status_history_actor_type_actor_id_index'],
        'reviews' => ['reviews_idol_id_is_hidden_created_at_index'],
        'content_pack_purchases' => ['content_pack_purchases_user_id_viewed_at_index'],
        'wallet_transactions' => ['wallet_transactions_reference_type_reference_id_type_index'],
        'conversations' => ['conversations_is_support_closed_at_updated_at_index'],
    ];

    foreach ($expectedIndexes as $table => $indexes) {
        $existing = DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public' AND tablename = :tbl", ['tbl' => $table]);
        $existingNames = array_column($existing, 'indexname');

        foreach ($indexes as $index) {
            expect($existingNames)->toContain($index);
        }
    }
});
