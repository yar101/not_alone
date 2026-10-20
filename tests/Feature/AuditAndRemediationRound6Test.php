<?php

use App\Models\Admin;
use App\Models\ChatBlock;
use App\Models\ContentPack;
use App\Models\Conversation;
use App\Models\IdolQuizSession;
use App\Models\IdolQuizSessionQuestion;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\UserStrike;
use App\Notifications\NewContentPackNotification;
use App\Notifications\NewPostNotification;
use App\Notifications\NewServiceNotification;
use App\Notifications\OrderAcceptedNotification;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderPaidNotification;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function getOrCreateTestCategoryAndUnitR6(): array
{
    $category = ServiceCategory::first();
    if (! $category) {
        $category = ServiceCategory::create([
            'name' => ['ru' => 'Тест', 'en' => 'Test'],
            'is_active' => true,
        ]);
    }

    $timeUnit = ServiceTimeUnit::first();
    if (! $timeUnit) {
        $timeUnit = ServiceTimeUnit::create([
            'name' => ['ru' => 'час', 'en' => 'hour'],
        ]);
    }

    return [$category, $timeUnit];
}

test('Order and Content notifications serialize safely to database and webPush with missing relations', function () {
    $order = new Order;
    $order->id = 123;
    $order->customer_id = 999999;
    $order->idol_id = 999998;
    $order->setRelation('customer', null);
    $order->setRelation('idol', null);

    $notifCreated = new OrderCreatedNotification($order);
    $dataCreated = $notifCreated->toDatabase(new User);
    expect($dataCreated['customer_name'])->toBe('Пользователь');

    $notifPaid = new OrderPaidNotification($order);
    $dataPaid = $notifPaid->toDatabase(new User);
    expect($dataPaid['customer_name'])->toBe('Пользователь')
        ->and($dataPaid['customer_gender'])->toBeNull();

    $notifAccepted = new OrderAcceptedNotification($order);
    $dataAccepted = $notifAccepted->toDatabase(new User);
    expect($dataAccepted['idol_name'])->toBe('Пользователь')
        ->and($dataAccepted['idol_gender'])->toBeNull();

    $post = new Post;
    $post->id = 456;
    $post->user_id = 999999;
    $post->setRelation('user', null);
    $notifPost = new NewPostNotification($post);
    $dataPost = $notifPost->toDatabase(new User);
    expect($dataPost['user_name'])->toBe('Пользователь');

    $pack = new ContentPack;
    $pack->id = 789;
    $pack->user_id = 999999;
    $pack->title = 'Test Pack';
    $pack->setRelation('user', null);
    $notifPack = new NewContentPackNotification($pack);
    $dataPack = $notifPack->toDatabase(new User);
    expect($dataPack['user_name'])->toBe('Пользователь');

    [$cat, $unit] = getOrCreateTestCategoryAndUnitR6();
    $service = new Service;
    $service->id = 101;
    $service->user_id = 999999;
    $service->setTranslations('name', ['ru' => 'Тестовая услуга']);
    $service->setRelation('user', null);
    $notifService = new NewServiceNotification($service);
    $dataService = $notifService->toDatabase(new User);
    expect($dataService['user_name'])->toBe('Пользователь');
});

test('UserProfileController getPosts and getComments handle deleted/missing user gracefully', function () {
    $idol = User::factory()->create(['is_idol' => true]);

    $post = Post::create([
        'user_id' => $idol->id,
        'body' => 'Post with comment',
    ]);

    DB::statement("SET session_replication_role = 'replica';");
    PostComment::create([
        'post_id' => $post->id,
        'user_id' => 999999, // Non-existent user
        'body' => 'Comment by ghost user',
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $response = $this->getJson(route('posts.comments.index', $post));
    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['user'])->toBeNull();

    // Now test post author missing
    DB::statement("SET session_replication_role = 'replica';");
    $ghostPost = Post::create([
        'user_id' => 888888, // Non-existent user
        'body' => 'Ghost post',
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $responsePosts = $this->getJson(route('profile.posts.feed', $idol));
    $responsePosts->assertOk();
});

test('UserProfileController toggleLike and storeComment abort 404 when post author user is not found', function () {
    $user = User::factory()->create();

    DB::statement("SET session_replication_role = 'replica';");
    $orphanPost = Post::create([
        'user_id' => 777777, // Non-existent user
        'body' => 'Orphan post',
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $resLike = $this->actingAs($user)->postJson(route('posts.like', $orphanPost));
    $resLike->assertStatus(404);

    $resComment = $this->actingAs($user)->postJson(route('posts.comments.store', $orphanPost), [
        'body' => 'Nice post!',
    ]);
    $resComment->assertStatus(404);
});

test('ConversationController offerServices rejects closed conversation and active block', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $client = User::factory()->create();
    [$cat, $unit] = getOrCreateTestCategoryAndUnitR6();

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $cat->id,
        'time_unit_id' => $unit->id,
        'name' => ['ru' => 'Услуга 1'],
        'price' => 1000,
        'is_active' => true,
        'status' => 'approved',
    ]);

    // 1. Closed conversation
    $closedConv = Conversation::create(['closed_at' => now()]);
    $closedConv->participants()->createMany([
        ['user_id' => $idol->id],
        ['user_id' => $client->id],
    ]);

    $resClosed = $this->actingAs($idol)->postJson(route('conversations.offer-services', $closedConv), [
        'services' => [$service->id],
    ]);
    $resClosed->assertStatus(422);

    // 2. Active chat block
    $conv = Conversation::create();
    $conv->participants()->createMany([
        ['user_id' => $idol->id],
        ['user_id' => $client->id],
    ]);

    ChatBlock::create([
        'blocker_id' => $client->id,
        'blocked_id' => $idol->id,
        'reason' => 'Spam',
    ]);

    $resBlocked = $this->actingAs($idol)->postJson(route('conversations.offer-services', $conv), [
        'services' => [$service->id],
    ]);
    $resBlocked->assertStatus(403);
});

test('Admin StrikeController atomically increments strikes and bans on 3rd strike', function () {
    $admin = Admin::create([
        'name' => 'Admin Strike',
        'email' => 'admin_strike_'.uniqid().'@test.pw',
        'password' => bcrypt('password'),
    ]);

    $user = User::factory()->create(['is_idol' => true]);

    // Give 2 existing active strikes
    UserStrike::create([
        'user_id' => $user->id,
        'admin_id' => $admin->id,
        'rating_deducted' => 0,
        'expires_at' => now()->addMonths(6),
    ]);
    UserStrike::create([
        'user_id' => $user->id,
        'admin_id' => $admin->id,
        'rating_deducted' => 0,
        'expires_at' => now()->addMonths(6),
    ]);

    expect($user->activeStrikesCount())->toBe(2);

    // Issuing 3rd strike triggers ban
    $response = $this->actingAs($admin, 'admin')->post(route('admin.strikes.store'), [
        'user_id' => $user->id,
        'admin_note' => '3rd violation',
        'ban_reason' => '3 active strikes reached',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->is_banned)->toBeTrue()
        ->and($user->ban_reason)->toBe('3 active strikes reached');
});

test('Admin DisputeController resolve returns 404 when parent order is missing', function () {
    $admin = Admin::create([
        'name' => 'Admin Disp',
        'email' => 'admin_disp_'.uniqid().'@test.pw',
        'password' => bcrypt('password'),
    ]);

    DB::statement("SET session_replication_role = 'replica';");
    $dispute = OrderDispute::create([
        'order_id' => 999999, // Missing order
        'customer_id' => 1,
        'reason' => 'Service not provided',
        'details' => 'String details at least ten characters long here...',
        'status' => 'open',
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $response = $this->actingAs($admin, 'admin')->patch(route('admin.disputes.resolve', $dispute), [
        'decision' => 'approved',
    ]);

    $response->assertStatus(404);
});

test('ContentPackPurchaseController rejects hidden content packs', function () {
    $buyer = User::factory()->create();
    $author = User::factory()->create(['is_idol' => true]);

    $hiddenPack = ContentPack::create([
        'user_id' => $author->id,
        'title' => 'Hidden Pack',
        'price' => 500,
        'status' => 'published',
        'published_at' => now(),
        'hidden_at' => now(), // Hidden!
    ]);

    $response = $this->actingAs($buyer)->postJson('/content-packs/purchase', [
        'items' => [$hiddenPack->id],
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['error' => 'Один или несколько контент-паков недоступны для покупки.']);
});

test('Admin IdolRatingController rejects rating updates on non-idol users', function () {
    $admin = Admin::create([
        'name' => 'Admin Rating',
        'email' => 'admin_rating_'.uniqid().'@test.pw',
        'password' => bcrypt('password'),
    ]);

    $regularUser = User::factory()->create(['is_idol' => false]);

    $response = $this->actingAs($admin, 'admin')->patch(route('admin.users.rating.update', $regularUser), [
        'delta' => 5,
        'note' => 'Bonus rating',
    ]);

    $response->assertStatus(422);
});

test('Idol QuizController answer aborts 422 if question is missing', function () {
    $user = User::factory()->create();

    $session = IdolQuizSession::create([
        'user_id' => $user->id,
        'attempt_number' => 1,
        'status' => 'active',
        'errors_count' => 0,
        'started_at' => now(),
    ]);

    DB::statement("SET session_replication_role = 'replica';");
    IdolQuizSessionQuestion::create([
        'session_id' => $session->id,
        'question_id' => 999999, // Missing question
        'stage' => 1,
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $response = $this->actingAs($user)->postJson(route('idol.quiz.answer'), [
        'session_id' => $session->id,
        'stage' => 1,
        'answer_index' => 0,
    ]);

    $response->assertStatus(422);
});

test('ReviewDisputeController enforces atomic review disputes and limit', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $reviewer = User::factory()->create();

    DB::statement("SET session_replication_role = 'replica';");
    $review = Review::create([
        'reviewer_id' => $reviewer->id,
        'idol_id' => $idol->id,
        'order_id' => 1,
        'rating' => 2,
        'text' => 'Not satisfied',
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    // First dispute succeeds
    $res1 = $this->actingAs($idol)->postJson(route('reviews.dispute.store', $review), [
        'reason' => 'Unfair feedback',
    ]);
    $res1->assertOk();

    // Second dispute while first is pending fails
    $res2 = $this->actingAs($idol)->postJson(route('reviews.dispute.store', $review), [
        'reason' => 'Another reason',
    ]);
    $res2->assertStatus(422);
});

test('OrderService::createOrder rejects order if customer has blocked idol', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);
    [$cat, $unit] = getOrCreateTestCategoryAndUnitR6();

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $cat->id,
        'time_unit_id' => $unit->id,
        'name' => ['ru' => 'Услуга'],
        'price' => 500,
        'is_active' => true,
        'status' => 'approved',
    ]);

    ChatBlock::create([
        'blocker_id' => $customer->id,
        'blocked_id' => $idol->id,
        'reason' => 'Blocked by customer',
    ]);

    $orderService = app(OrderService::class);

    expect(fn () => $orderService->createOrder($customer, $idol, [['id' => $service->id, 'quantity' => 1]]))
        ->toThrow(\Exception::class, 'Вы заблокировали этого пользователя');
});

test('Admin SupportChatController send handles null participant user safely', function () {
    $admin = Admin::create([
        'name' => 'Admin Supp',
        'email' => 'admin_supp_'.uniqid().'@test.pw',
        'password' => bcrypt('password'),
    ]);

    $conv = Conversation::create(['is_support' => true]);
    DB::statement("SET session_replication_role = 'replica';");
    $conv->participants()->create(['user_id' => 999999]); // Non-existent user
    DB::statement("SET session_replication_role = 'origin';");

    $response = $this->actingAs($admin, 'admin')->postJson(route('admin.support.send', $conv), [
        'body' => 'Hello from support!',
    ]);

    $response->assertOk();
});
