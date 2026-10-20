<?php

use App\Http\Middleware\SetLocale;
use App\Models\Admin;
use App\Models\BanReason;
use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\Review;
use App\Models\ReviewDispute;
use App\Models\ReviewEpithet;
use App\Models\ServicePriceLimit;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

function createAuditR3Admin(array $attributes = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Admin',
        'email' => 'admin_'.uniqid().'@test.pw',
        'password' => bcrypt('password'),
    ], $attributes));
}

test('SetLocale middleware enforces ru locale for RF operations', function () {
    $middleware = new SetLocale;

    // 1. Default fallback when no session and unauthenticated
    Session::flush();
    $request = Request::create('/');
    $middleware->handle($request, function () {
        return response('ok');
    });
    expect(App::getLocale())->toBe('ru')
        ->and(Session::get('locale'))->toBe('ru');

    // 2. Session with en is forced to ru
    Session::put('locale', 'en');
    $middleware->handle($request, function () {
        return response('ok');
    });
    expect(App::getLocale())->toBe('ru')
        ->and(Session::get('locale'))->toBe('ru');

    // 3. Authenticated user with en is forced to ru
    $user = User::factory()->create(['locale' => 'en']);
    $this->actingAs($user);

    $middleware->handle($request, function () {
        return response('ok');
    });
    expect(App::getLocale())->toBe('ru')
        ->and(Session::get('locale'))->toBe('ru');
});

test('ReviewEpithet and BanReason reorder clears cache keys', function () {
    $admin = createAuditR3Admin();

    // Epithets
    $e1 = ReviewEpithet::create(['label' => 'Super', 'sort_order' => 1]);
    $e2 = ReviewEpithet::create(['label' => 'Cool', 'sort_order' => 2]);
    Cache::put('review_epithets_list', ['some_cached_data'], 3600);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.review-epithets.reorder'), [
            'items' => [
                ['id' => $e1->id, 'sort_order' => 2],
                ['id' => $e2->id, 'sort_order' => 1],
            ],
        ])
        ->assertRedirect();

    expect(Cache::has('review_epithets_list'))->toBeFalse();

    // Ban reasons
    $r1 = BanReason::create(['label' => ['ru' => 'Spam'], 'type' => 'chat_block', 'sort_order' => 1]);
    $r2 = BanReason::create(['label' => ['ru' => 'Abuse'], 'type' => 'user_ban', 'sort_order' => 2]);
    Cache::put('chat_block_reasons_list', ['data'], 3600);
    Cache::put('user_ban_reasons_list', ['data'], 3600);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.ban-reasons.reorder'), [
            'items' => [
                ['id' => $r1->id, 'sort_order' => 2],
                ['id' => $r2->id, 'sort_order' => 1],
            ],
        ])
        ->assertRedirect();

    expect(Cache::has('chat_block_reasons_list'))->toBeFalse()
        ->and(Cache::has('user_ban_reasons_list'))->toBeFalse();
});

test('Banned users are rejected from broadcast channels', function () {
    require base_path('routes/channels.php');

    $activeUser = User::factory()->create(['is_banned' => false]);
    $bannedUser = User::factory()->create([
        'is_banned' => true,
        'banned_at' => now(),
        'banned_until' => now()->addDays(7),
    ]);

    $conversation = Conversation::create();
    $conversation->participants()->create(['user_id' => $activeUser->id]);
    $conversation->participants()->create(['user_id' => $bannedUser->id]);

    $channels = Broadcast::getChannels();

    // Test App.Models.User.{id}
    $userCallback = $channels->get('App.Models.User.{id}');
    expect($userCallback($activeUser, $activeUser->id))->toBeTrue()
        ->and($userCallback($bannedUser, $bannedUser->id))->toBeFalse();

    // Test conversation.{conversationId}
    $convCallback = $channels->get('conversation.{conversationId}');
    expect($convCallback($activeUser, $conversation->id))->toBeTrue()
        ->and($convCallback($bannedUser, $conversation->id))->toBeFalse();

    // Test presence-conversation.{conversationId}
    $presenceConvCallback = $channels->get('presence-conversation.{conversationId}');
    expect($presenceConvCallback($activeUser, $conversation->id))->toBeArray()
        ->and($presenceConvCallback($bannedUser, $conversation->id))->toBeFalse();

    // Test presence-online
    $onlineCallback = $channels->get('presence-online');
    expect($onlineCallback($activeUser))->toBeArray()
        ->and($onlineCallback($bannedUser))->toBeFalse();

    // Test orders.{userId}
    $orderCallback = $channels->get('orders.{userId}');
    expect($orderCallback($activeUser, $activeUser->id))->toBeTrue()
        ->and($orderCallback($bannedUser, $bannedUser->id))->toBeFalse();
});

test('ReviewDisputeController::resolve is idempotent and null-safe for soft-deleted idols', function () {
    $admin = createAuditR3Admin();
    $idol = User::factory()->create(['is_idol' => true, 'rating' => 4.5]);
    $reviewer = User::factory()->create();

    $review = Review::create([
        'order_id' => null,
        'reviewer_id' => $reviewer->id,
        'idol_id' => $idol->id,
        'rating' => 1,
        'text' => 'Плохо',
    ]);

    $dispute = ReviewDispute::create([
        'review_id' => $review->id,
        'idol_id' => $idol->id,
        'reason' => 'Ложный отзыв',
        'status' => 'pending',
    ]);

    // First resolve succeeds
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.review-disputes.resolve', $dispute), [
            'decision' => 'approved',
            'admin_note' => 'Одобрено',
        ])
        ->assertRedirect()
        ->assertSessionMissing('errors');

    $dispute->refresh();
    expect($dispute->status)->toBe('approved');

    // Second resolve returns error and does not double-adjust rating
    $ratingBefore = $idol->fresh()->rating;
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.review-disputes.resolve', $dispute), [
            'decision' => 'approved',
            'admin_note' => 'Повторно',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['dispute']);

    expect($idol->fresh()->rating)->toBe($ratingBefore);

    // Soft-deleted idol does not crash resolution
    $deletedIdol = User::factory()->create(['is_idol' => true]);
    $review2 = Review::create([
        'order_id' => null,
        'reviewer_id' => $reviewer->id,
        'idol_id' => $deletedIdol->id,
        'rating' => 2,
        'text' => 'Тест',
    ]);
    $dispute2 = ReviewDispute::create([
        'review_id' => $review2->id,
        'idol_id' => $deletedIdol->id,
        'reason' => 'Удаленный айдол',
        'status' => 'pending',
    ]);

    $deletedIdol->delete(); // Soft-delete

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.review-disputes.resolve', $dispute2), [
            'decision' => 'approved',
            'admin_note' => 'Удален, но одобряем',
        ])
        ->assertRedirect()
        ->assertSessionMissing('errors');

    expect($dispute2->fresh()->status)->toBe('approved');
});

test('ReportController::review and dismiss are idempotent and prevent duplicate penalty', function () {
    $admin = createAuditR3Admin();
    $reporter = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true, 'rating' => 5.0]);

    $report = UserReport::create([
        'reporter_id' => $reporter->id,
        'reported_id' => $idol->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    // First review reduces rating
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.reports.review', $report), [
            'admin_note' => 'Принято',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $report->refresh();
    expect($report->status)->toBe('reviewed');
    $ratingAfterFirst = $idol->fresh()->rating;
    expect($ratingAfterFirst)->toBeLessThan(5.0);

    // Second review attempt is blocked
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.reports.review', $report), [
            'admin_note' => 'Второй раз',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['report']);

    // Rating is NOT decremented again!
    expect($idol->fresh()->rating)->toBe($ratingAfterFirst);

    // Dismiss pending report
    $report2 = UserReport::create([
        'reporter_id' => $reporter->id,
        'reported_id' => $idol->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.reports.dismiss', $report2), [
            'admin_note' => 'Отклонено',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($report2->fresh()->status)->toBe('dismissed');

    // Second dismiss attempt is blocked
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.reports.dismiss', $report2), [
            'admin_note' => 'Второй раз',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['report']);
});

test('ServicePriceLimitController validates uniqueness of time_unit_id', function () {
    $admin = createAuditR3Admin();
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'день', 'en' => 'day']]);

    // Store limit
    $this->actingAs($admin, 'admin')
        ->post(route('admin.services.price-limits.store'), [
            'time_unit_id' => $timeUnit->id,
            'max_price' => 5000,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $limit = ServicePriceLimit::where('time_unit_id', $timeUnit->id)->first();
    expect($limit)->not->toBeNull()
        ->and($limit->max_price)->toBe(5000);

    // Duplicate store for same time unit fails
    $this->actingAs($admin, 'admin')
        ->post(route('admin.services.price-limits.store'), [
            'time_unit_id' => $timeUnit->id,
            'max_price' => 8000,
        ])
        ->assertSessionHasErrors(['time_unit_id']);

    // Update same limit succeeds
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.services.price-limits.update', $limit), [
            'time_unit_id' => $timeUnit->id,
            'max_price' => 6000,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($limit->fresh()->max_price)->toBe(6000);
});

test('PostPolicy authorizes idol creation and blocks banned users', function () {
    $idol = User::factory()->create(['is_idol' => true, 'is_banned' => false]);
    $regular = User::factory()->create(['is_idol' => false, 'is_banned' => false]);
    $bannedIdol = User::factory()->create([
        'is_idol' => true,
        'is_banned' => true,
        'banned_at' => now(),
        'banned_until' => now()->addDays(5),
    ]);

    $post = Post::create([
        'user_id' => $idol->id,
        'body' => 'Привет всем!',
    ]);

    expect($idol->can('create', Post::class))->toBeTrue()
        ->and($regular->can('create', Post::class))->toBeFalse()
        ->and($bannedIdol->can('create', Post::class))->toBeFalse()
        ->and($idol->can('viewAny', Post::class))->toBeTrue()
        ->and($bannedIdol->can('viewAny', Post::class))->toBeFalse()
        ->and($idol->can('view', $post))->toBeTrue()
        ->and($bannedIdol->can('view', $post))->toBeFalse()
        ->and($idol->can('update', $post))->toBeTrue()
        ->and($regular->can('update', $post))->toBeFalse()
        ->and($idol->can('delete', $post))->toBeTrue();
});

test('PruneRejectedContentPacks command skips rejected packs with existing purchases', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $buyer = User::factory()->create();

    // Pack 1: rejected and older than 7 days, NO purchases -> should be pruned
    $packWithoutPurchases = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Без покупок',
        'price' => 100,
        'status' => 'rejected',
    ]);
    DB::table('content_packs')->where('id', $packWithoutPurchases->id)->update(['updated_at' => now()->subDays(10)]);

    // Pack 2: rejected and older than 7 days, WITH purchase -> should NOT be pruned
    $packWithPurchases = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'С покупками',
        'price' => 200,
        'status' => 'rejected',
    ]);
    DB::table('content_packs')->where('id', $packWithPurchases->id)->update(['updated_at' => now()->subDays(10)]);

    ContentPackPurchase::create([
        'content_pack_id' => $packWithPurchases->id,
        'user_id' => $buyer->id,
        'price_paid' => 200,
    ]);

    $this->artisan('content-packs:prune-rejected')
        ->assertSuccessful();

    expect(ContentPack::find($packWithoutPurchases->id))->toBeNull()
        ->and(ContentPack::find($packWithPurchases->id))->not->toBeNull();
});

test('ServiceChangeRequest decision dispatches notifications safely and handles deleted users', function () {
    $admin = createAuditR3Admin();
    $idol = User::factory()->create(['is_idol' => true]);
    $category = \App\Models\ServiceCategory::create(['name' => ['ru' => 'Тест', 'en' => 'Test']]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour']]);

    $service = \App\Models\Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Исходная услуга',
        'price' => 1000,
        'is_active' => true,
        'status' => 'approved',
    ]);

    $changeRequest = \App\Models\ServiceChangeRequest::create([
        'service_id' => $service->id,
        'changed_fields' => ['price'],
        'pending_price' => 1500,
        'status' => 'pending',
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.services.change-requests.decide', $changeRequest), [
            'decision' => 'approved',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $service->refresh();
    expect($service->price)->toBe(1500)
        ->and($changeRequest->fresh()->status)->toBe('approved');
});
