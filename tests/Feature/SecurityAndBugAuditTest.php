<?php

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\AdminBroadcast;
use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\IdolApplication;
use App\Models\Message;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Post;
use App\Models\Review;
use App\Models\ReviewDispute;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Notifications\TestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('AdminBroadcast is_idol boolean filtering matches idols and excludes non-idols', function () {
    $admin = Admin::create([
        'name' => 'Admin',
        'email' => 'admin@test.local',
        'password' => bcrypt('password'),
    ]);

    $idol = User::factory()->create(['is_idol' => true]);
    $normalUser = User::factory()->create(['is_idol' => false]);

    // Broadcast targeted to idols only
    $broadcastForIdols = AdminBroadcast::create([
        'admin_id' => $admin->id,
        'title' => ['ru' => 'Для айдолов', 'en' => 'For idols'],
        'body' => ['ru' => 'Текст', 'en' => 'Text'],
        'target' => 'filtered',
        'target_filters' => ['is_idol' => true],
    ]);

    // Broadcast targeted to non-idols only
    $broadcastForNonIdols = AdminBroadcast::create([
        'admin_id' => $admin->id,
        'title' => ['ru' => 'Для клиентов', 'en' => 'For clients'],
        'body' => ['ru' => 'Текст', 'en' => 'Text'],
        'target' => 'filtered',
        'target_filters' => ['is_idol' => false],
    ]);

    // Query for idol user: should include broadcastForIdols and exclude broadcastForNonIdols
    $idolBroadcastIds = AdminBroadcast::where('target', '!=', 'user')->forUser($idol)->pluck('id')->all();
    expect($idolBroadcastIds)->toContain($broadcastForIdols->id);
    expect($idolBroadcastIds)->not->toContain($broadcastForNonIdols->id);

    // Query for non-idol user: should include broadcastForNonIdols and exclude broadcastForIdols
    $normalBroadcastIds = AdminBroadcast::where('target', '!=', 'user')->forUser($normalUser)->pluck('id')->all();
    expect($normalBroadcastIds)->toContain($broadcastForNonIdols->id);
    expect($normalBroadcastIds)->not->toContain($broadcastForIdols->id);
});

test('Review latestDispute relationship loads latest dispute correctly for all reviews without batch truncation', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $reviewer1 = User::factory()->create();
    $reviewer2 = User::factory()->create();

    $review1 = Review::create([
        'reviewer_id' => $reviewer1->id,
        'idol_id' => $idol->id,
        'rating' => 5,
        'text' => 'Первый отзыв',
    ]);

    $review2 = Review::create([
        'reviewer_id' => $reviewer2->id,
        'idol_id' => $idol->id,
        'rating' => 4,
        'text' => 'Второй отзыв',
    ]);

    // Review 1 has an older dispute and a newer dispute
    $d1 = ReviewDispute::create([
        'review_id' => $review1->id,
        'idol_id' => $idol->id,
        'reason' => 'Old dispute',
        'status' => 'rejected',
    ]);
    $d1->created_at = now()->subDays(2);
    $d1->save();

    $d2 = ReviewDispute::create([
        'review_id' => $review1->id,
        'idol_id' => $idol->id,
        'reason' => 'New dispute',
        'status' => 'pending',
    ]);
    $d2->created_at = now()->subDay();
    $d2->save();

    // Review 2 has 1 dispute
    ReviewDispute::create([
        'review_id' => $review2->id,
        'idol_id' => $idol->id,
        'reason' => 'Dispute for review 2',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($idol)->getJson(route('users.reviews', $idol));
    $response->assertOk();

    $items = collect($response->json('reviews'));
    expect($items)->toHaveCount(2);

    $r1 = $items->firstWhere('id', $review1->id);
    $r2 = $items->firstWhere('id', $review2->id);

    expect($r1['dispute_status'])->toBe('pending');
    expect($r2['dispute_status'])->toBe('approved');
});

test('ConversationController show only marks notifications for exact conversation_id', function () {
    Event::fake();

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $convShort = Conversation::create();
    $convShort->participants()->createMany([
        ['user_id' => $user->id],
        ['user_id' => $otherUser->id],
    ]);

    $convLong = Conversation::create();
    $convLong->participants()->createMany([
        ['user_id' => $user->id],
        ['user_id' => $otherUser->id],
    ]);

    $msgShort = Message::create([
        'conversation_id' => $convShort->id,
        'sender_id' => $otherUser->id,
        'body' => 'Short conv message',
        'type' => 'text',
    ]);

    $msgLong = Message::create([
        'conversation_id' => $convLong->id,
        'sender_id' => $otherUser->id,
        'body' => 'Long conv message',
        'type' => 'text',
    ]);

    // Notify user with both notifications
    $user->notify(new NewMessageNotification($msgShort));
    $user->notify(new NewMessageNotification($msgLong));

    expect($user->unreadNotifications()->count())->toBe(2);

    // View convShort
    $response = $this->actingAs($user)->getJson(route('conversations.show', $convShort));
    $response->assertOk();

    // Notification for convShort should be read, notification for convLong must remain unread
    $remainingUnread = $user->unreadNotifications()->get();
    expect($remainingUnread)->toHaveCount(1);
    expect($remainingUnread->first()->data['conversation_id'])->toBe($convLong->id);
});

test('NotificationController combined correctly indicates has_more when items exceed PER_PAGE', function () {
    $user = User::factory()->create();

    for ($i = 1; $i <= 21; $i++) {
        $user->notify(new TestNotification("Message {$i}"));
    }

    $response = $this->actingAs($user)->getJson(route('notifications.combined'));
    $response->assertOk();

    expect($response->json('has_more'))->toBeTrue();
    expect($response->json('items'))->toHaveCount(20);
});

test('UserProfileController toggleLike and storeComment reject blocked users with 403', function () {
    $author = User::factory()->create();
    $visitor = User::factory()->create();

    $post = Post::create([
        'user_id' => $author->id,
        'body' => 'Test author post',
    ]);

    // Active chat block between author and visitor
    ChatBlock::create([
        'blocker_id' => $author->id,
        'blocked_id' => $visitor->id,
        'reason' => 'Blocked user',
    ]);

    // Visitor attempts to like
    $likeResponse = $this->actingAs($visitor)->postJson(route('posts.like', $post));
    $likeResponse->assertStatus(403);

    // Visitor attempts to comment
    $commentResponse = $this->actingAs($visitor)->postJson(route('posts.comments.store', $post), [
        'body' => 'Blocked comment',
    ]);
    $commentResponse->assertStatus(403);
});

test('OrderService addItem forbids adding trial services to existing pending orders', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $category = ServiceCategory::create(['name' => ['ru' => 'Услуга', 'en' => 'Service']]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour']]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Pending,
    ]);

    $trialService = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Ознакомительная услуга',
        'price' => 0,
        'is_trial' => true,
        'is_active' => true,
        'status' => 'approved',
    ]);

    $response = $this->actingAs($customer)->postJson(route('orders.items.add', $order), [
        'service_id' => $trialService->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['error' => 'Ознакомительную услугу можно оформить только при создании первого заказа.']);
});

test('OrderPolicy confirmCompletion allows confirming completion when order is already Completed', function () {
    Event::fake();

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
        'completion_confirmed_by_customer' => true,
        'completion_confirmed_by_idol' => false,
    ]);

    $response = $this->actingAs($idol)->patchJson(route('orders.confirm-completion', $order));
    $response->assertOk();
    $response->assertJson([
        'confirmed' => true,
    ]);
});

test('Admin OrderController index handles soft-deleted or missing customer/idol without crashing', function () {
    $admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin_orders@test.local',
        'password' => bcrypt('password'),
    ]);

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Pending,
    ]);

    // Soft delete customer
    $customer->delete();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.orders.index'));
    $response->assertOk();
});

test('Idol ApplicationController cleans up old face photo from storage on reapplication', function () {
    Storage::fake();

    $user = User::factory()->create([
        'idol_quiz_passed_at' => now(),
    ]);

    // Existing rejected application with a stored photo
    $oldPhotoPath = UploadedFile::fake()->image('old_photo.jpg')->store('idol-photos');
    expect(Storage::exists($oldPhotoPath))->toBeTrue();

    IdolApplication::create([
        'user_id' => $user->id,
        'face_photo_path' => $oldPhotoPath,
        'status' => 'rejected',
        'rejection_reason' => 'Плохое качество фото',
    ]);

    $newFile = UploadedFile::fake()->image('new_photo.jpg');

    $response = $this->actingAs($user)->post(route('idol.apply.store'), [
        'face_photo' => $newFile,
    ]);

    $response->assertRedirect(route('idol.apply'));

    // Old photo should be deleted from storage
    expect(Storage::exists($oldPhotoPath))->toBeFalse();

    // New application should exist with a new photo path
    $newApp = $user->fresh()->idolApplication;
    expect($newApp)->not->toBeNull();
    expect($newApp->status)->toBe('pending');
    expect(Storage::exists($newApp->face_photo_path))->toBeTrue();
});

test('platform fee settings default to 4% deposit, 4% withdrawal, and 10% platform fee', function () {
    expect((float) PlatformSetting::get('deposit_fee_percent'))->toBe(4.0);
    expect((float) PlatformSetting::get('withdrawal_fee_percent'))->toBe(4.0);
    expect((float) PlatformSetting::get('platform_fee_percent'))->toBe(10.0);

    expect(config('services.payments.deposit_fee_percent'))->toBe(4.0);
    expect(config('services.payments.withdrawal_fee_percent'))->toBe(4.0);
    expect(config('services.payments.platform_fee_percent'))->toBe(10.0);
});

test('test suite execution is isolated and does not flush or poison Redis DB 0 or DB 1', function () {
    expect(config('cache.default'))->toBe('array');
    expect(config('filesystems.default'))->toBe('local');
    expect(config('broadcasting.default'))->toBe('null');
    expect(config('mail.default'))->toBe('array');
    expect(config('database.redis.default.database'))->toBe(15);
    expect(config('database.redis.cache.database'))->toBe(15);
    expect(config('database.redis.options.prefix'))->toBe('test_');
});
