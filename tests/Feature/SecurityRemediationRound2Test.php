<?php

use App\Models\Admin;
use App\Models\ChatBlock;
use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\Conversation;
use App\Models\InterestSuggestion;
use App\Models\Order;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Review;
use App\Models\ReviewDispute;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\TraitSuggestion;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('OrderService::createOrder rejects unapproved services', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $category = ServiceCategory::create(['name' => ['ru' => 'Тест', 'en' => 'Test']]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour']]);

    $pendingService = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Неодобренная услуга',
        'price' => 500,
        'is_active' => true,
        'status' => 'pending',
    ]);

    $orderService = app(OrderService::class);

    expect(fn () => $orderService->createOrder($customer, $idol, [
        ['id' => $pendingService->id, 'quantity' => 1],
    ]))->toThrow(\Exception::class, 'Некоторые услуги недоступны');
});

test('ConversationController::offerServices rejects unapproved services', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $customer = User::factory()->create();

    $conversation = Conversation::create();
    $conversation->participants()->createMany([
        ['user_id' => $idol->id],
        ['user_id' => $customer->id],
    ]);

    $category = ServiceCategory::create(['name' => ['ru' => 'Тест', 'en' => 'Test']]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour']]);

    $rejectedService = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Отклоненная услуга',
        'price' => 500,
        'is_active' => true,
        'status' => 'rejected',
    ]);

    $response = $this->actingAs($idol)->postJson(route('conversations.offer-services', $conversation), [
        'services' => [$rejectedService->id],
    ]);

    $response->assertStatus(422);
});

test('idol can dispute reviews with rating 1 to 5 stars', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $review = Review::create([
        'reviewer_id' => $customer->id,
        'idol_id' => $idol->id,
        'rating' => 5,
        'text' => '5 звезд но оскорбительный текст',
    ]);

    $response = $this->actingAs($idol)->postJson(route('reviews.dispute.store', $review), [
        'reason' => 'Неприемлемый текст в отзыве',
    ]);

    $response->assertOk();
    expect(ReviewDispute::where('review_id', $review->id)->exists())->toBeTrue();
});

test('idol cannot submit a new dispute while one is pending', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $review = Review::create([
        'reviewer_id' => $customer->id,
        'idol_id' => $idol->id,
        'rating' => 2,
        'text' => 'Спорный отзыв',
    ]);

    ReviewDispute::create([
        'review_id' => $review->id,
        'idol_id' => $idol->id,
        'reason' => 'Первая жалоба',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($idol)->postJson(route('reviews.dispute.store', $review), [
        'reason' => 'Повторная жалоба пока первая на рассмотрении',
    ]);

    $response->assertStatus(422);
});

test('idol cannot dispute a review more than 2 times', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $review = Review::create([
        'reviewer_id' => $customer->id,
        'idol_id' => $idol->id,
        'rating' => 1,
        'text' => 'Плохо',
    ]);

    // Dispute 1 (rejected by admin)
    ReviewDispute::create([
        'review_id' => $review->id,
        'idol_id' => $idol->id,
        'reason' => 'Жалоба 1',
        'status' => 'rejected',
        'resolved_at' => now(),
    ]);

    // Dispute 2 (also rejected by admin)
    ReviewDispute::create([
        'review_id' => $review->id,
        'idol_id' => $idol->id,
        'reason' => 'Жалоба 2',
        'status' => 'rejected',
        'resolved_at' => now(),
    ]);

    // Dispute 3 must be blocked because max 2 disputes allowed
    $response = $this->actingAs($idol)->postJson(route('reviews.dispute.store', $review), [
        'reason' => 'Жалоба 3',
    ]);

    $response->assertStatus(422);
});

test('UserProfileController::toggleLike handles duplicate like gracefully without 500 error', function () {
    $author = User::factory()->create();
    $post = Post::create(['user_id' => $author->id, 'body' => 'Hello post']);

    $liker = User::factory()->create();

    // First toggle -> liked
    $r1 = $this->actingAs($liker)->postJson(route('posts.like', $post));
    $r1->assertOk()->assertJson(['liked' => true, 'likes_count' => 1]);

    // Toggle again -> unliked
    $r2 = $this->actingAs($liker)->postJson(route('posts.like', $post));
    $r2->assertOk()->assertJson(['liked' => false, 'likes_count' => 0]);
});

test('ContentPackController::store rejects cover_index out of bounds', function () {
    Storage::fake('public');
    $idol = User::factory()->create(['is_idol' => true]);

    $file1 = UploadedFile::fake()->image('photo1.jpg');
    $file2 = UploadedFile::fake()->image('photo2.jpg');

    $response = $this->actingAs($idol)->postJson(route('content-packs.store'), [
        'title' => 'Test Pack',
        'price' => 500,
        'photos' => [$file1, $file2],
        'cover_index' => 5, // out of bounds (only 0 and 1 exist)
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['cover_index']);
});

test('ContentPackController::update reassigns cover_path to first remaining photo if cover is deleted', function () {
    Storage::fake('public');
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin_test_cp@example.com',
        'password' => bcrypt('password'),
    ]);
    $idol = User::factory()->create(['is_idol' => true]);

    $pack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Pack With Remarks',
        'price' => 500,
        'status' => 'has_remarks',
        'cover_path' => 'content-packs/cover.jpg',
    ]);

    $photo1 = ContentPackPhoto::create([
        'content_pack_id' => $pack->id,
        'path' => 'content-packs/cover.jpg',
        'original_filename' => 'cover.jpg',
        'sort_order' => 0,
    ]);

    $photo2 = ContentPackPhoto::create([
        'content_pack_id' => $pack->id,
        'path' => 'content-packs/second.jpg',
        'original_filename' => 'second.jpg',
        'sort_order' => 1,
    ]);

    $pack->reviews()->create([
        'admin_id' => $admin->id,
        'decision' => 'has_remarks',
        'flagged_fields' => [],
        'flagged_photo_ids' => [$photo1->id],
    ]);

    $response = $this->actingAs($idol)->patch(route('content-packs.update', $pack), [
        'delete_photo_ids' => [$photo1->id],
    ]);

    $response->assertRedirect();
    $pack->refresh();

    // cover_path should not be null; it should have fallen back to photo2
    expect($pack->cover_path)->toBe($photo2->path);
});

test('FollowController::toggle prevents following banned idols', function () {
    $follower = User::factory()->create();
    $bannedIdol = User::factory()->create([
        'is_idol' => true,
        'is_banned' => true,
        'banned_until' => now()->addDays(5),
    ]);

    $response = $this->actingAs($follower)->post(route('users.follow', $bannedIdol));

    $response->assertStatus(422);
    expect($follower->isFollowing($bannedIdol->id))->toBeFalse();
});

test('FollowController::toggle prevents following idol who blocked the user', function () {
    $follower = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    ChatBlock::create([
        'blocker_id' => $idol->id,
        'blocked_id' => $follower->id,
        'reason' => 'Нежелательные сообщения',
    ]);

    $response = $this->actingAs($follower)->post(route('users.follow', $idol));

    $response->assertStatus(403);
    expect($follower->isFollowing($idol->id))->toBeFalse();
});

test('UserProfileController limits pending interest suggestions to 10', function () {
    $user = User::factory()->create();

    // Create 10 pending suggestions
    for ($i = 1; $i <= 10; $i++) {
        InterestSuggestion::create([
            'user_id' => $user->id,
            'name' => 'Интерес '.$i,
            'status' => 'pending',
        ]);
    }

    $response = $this->actingAs($user)->post(route('profile.interest-suggestions.store'), [
        'name' => 'Одиннадцатый интерес',
    ]);

    $response->assertStatus(422);
    expect(InterestSuggestion::where('user_id', $user->id)->count())->toBe(10);
});

test('UserProfileController limits pending trait suggestions to 10', function () {
    $user = User::factory()->create();

    // Create 10 pending trait suggestions
    for ($i = 1; $i <= 10; $i++) {
        TraitSuggestion::create([
            'user_id' => $user->id,
            'name' => 'Черта '.$i,
            'status' => 'pending',
        ]);
    }

    $response = $this->actingAs($user)->post(route('profile.trait-suggestions.store'), [
        'name' => 'Одиннадцатая черта',
    ]);

    $response->assertStatus(422);
    expect(TraitSuggestion::where('user_id', $user->id)->count())->toBe(10);
});
