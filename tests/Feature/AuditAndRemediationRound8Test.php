<?php

use App\Enums\OrderStatus;
use App\Events\OrderCompletedEvent;
use App\Models\Admin;
use App\Models\AvatarFrame;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Post;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Services\AvatarFrames\ConditionRegistry;
use App\Services\AvatarFrames\Conditions\FrameCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createTestAdminR8(): Admin
{
    return Admin::create([
        'name' => 'Admin Test R8',
        'email' => 'admin_r8_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

function getOrCreateTestCategoryAndUnitR8(): array
{
    $category = ServiceCategory::first();
    if (! $category) {
        $category = ServiceCategory::create([
            'name' => ['ru' => 'Тестовая категория R8', 'en' => 'Test Category R8'],
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

test('NotificationController::orders handles notifications with missing type without throwing Undefined array key', function () {
    $user = User::factory()->create();

    // Insert a database notification record with no 'type' key in data
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\OrderCreatedNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'order_id' => 999,
            'customer_id' => 1,
            'customer_name' => 'Test Customer',
        ]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/notifications/orders');

    $response->assertOk()
        ->assertJsonStructure(['items', 'has_unread', 'has_more']);

    $items = $response->json('items');
    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('order')
        ->and($items[0]['order_id'])->toBe(999);
});

test('NotificationController::combined returns items as an indexed array', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/notifications/combined');

    $response->assertOk()
        ->assertJsonStructure(['items', 'has_more']);

    expect(is_array($response->json('items')))->toBeTrue();
});

test('OrderController::store creates order successfully even if idol notification throws', function () {
    [$category, $timeUnit] = getOrCreateTestCategoryAndUnitR8();

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Консультация R8',
        'price' => 500,
        'is_active' => true,
        'status' => 'approved',
    ]);

    // Throw exception on Notification facade to simulate mail/push provider crash
    Notification::shouldReceive('send')
        ->andThrow(new \RuntimeException('Push provider connection timed out'));

    $response = $this->actingAs($customer)->postJson('/orders', [
        'idol_id' => $idol->id,
        'services' => [
            ['id' => $service->id, 'quantity' => 1],
        ],
    ]);

    $response->assertOk()
        ->assertJsonStructure(['order_id', 'conversation_id']);

    $this->assertDatabaseHas('orders', [
        'id' => $response->json('order_id'),
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Pending->value,
    ]);
});

test('ConversationController::message succeeds even if recipient notification throws', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create(['is_idol' => true]);

    $conversation = Conversation::create([]);
    $conversation->participants()->createMany([
        ['user_id' => $user1->id],
        ['user_id' => $user2->id],
    ]);

    Notification::shouldReceive('send')
        ->andThrow(new \RuntimeException('WebPush delivery failed'));

    $response = $this->actingAs($user1)->postJson("/conversations/{$conversation->id}/messages", [
        'body' => 'Привет! Как дела?',
    ]);

    $response->assertOk()
        ->assertJsonPath('body', 'Привет! Как дела?')
        ->assertJsonPath('conversation_id', $conversation->id);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $user1->id,
        'body' => 'Привет! Как дела?',
    ]);
});

test('HelpArticleController and HelpCategoryController reorder reject empty ids array', function () {
    $admin = createTestAdminR8();

    $catResp = $this->actingAs($admin, 'admin')->post('/admin/help-categories/reorder', [
        'ids' => [],
    ]);
    $catResp->assertSessionHasErrors('ids');

    $artResp = $this->actingAs($admin, 'admin')->post('/admin/help-articles/reorder', [
        'ids' => [],
    ]);
    $artResp->assertSessionHasErrors('ids');
});

test('AutoCompleteOrdersCommand and ReleaseIdolHoldsCommand enforce minimum threshold safety', function () {
    \App\Models\PlatformSetting::set('order_auto_complete_delay', -5);
    \App\Models\PlatformSetting::set('order_dispute_window_minutes', -10);

    $this->artisan('orders:auto-complete')
        ->assertExitCode(0);

    $this->artisan('orders:release-idol-holds')
        ->assertExitCode(0);
});

test('UserProfileController::updateVoice deletes old voice file when format changes', function () {
    Storage::fake(config('filesystems.default'));

    $user = User::factory()->create([
        'voice_path' => 'voices/old_voice.webm',
    ]);

    Storage::disk(config('filesystems.default'))->put('voices/old_voice.webm', 'audio content');
    expect(Storage::disk(config('filesystems.default'))->exists('voices/old_voice.webm'))->toBeTrue();

    $newVoice = UploadedFile::fake()->create('new_voice.ogg', 500, 'audio/ogg');

    $response = $this->actingAs($user)->post('/profile/voice', [
        'voice' => $newVoice,
    ]);

    $response->assertRedirect();

    $user->refresh();
    expect($user->voice_path)->not->toBe('voices/old_voice.webm')
        ->and(Storage::disk(config('filesystems.default'))->exists('voices/old_voice.webm'))->toBeFalse()
        ->and(Storage::disk(config('filesystems.default'))->exists($user->voice_path))->toBeTrue();
});

test('UserProfileController::storeComment returns user object containing gender', function () {
    $user = User::factory()->create([
        'gender' => 'female',
    ]);

    $post = Post::create([
        'user_id' => $user->id,
        'body' => 'Пост для проверки структуры комментариев',
    ]);

    $response = $this->actingAs($user)->postJson("/posts/{$post->id}/comments", [
        'body' => 'Тестовый комментарий с проверкой gender',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'id',
            'body',
            'created_at',
            'user' => ['id', 'name', 'avatar_url', 'gender'],
            'replies_count',
            'replies',
        ])
        ->assertJsonPath('user.gender', 'female');
});

class BrokenConditionForTestR8 implements FrameCondition
{
    public function getKey(): string
    {
        return 'broken_condition_r8';
    }

    public function getDescription(): string
    {
        return 'Broken Condition';
    }

    public function check(User $user): bool
    {
        throw new \RuntimeException('Condition check exploded!');
    }
}

test('AchievementListener handles broken condition check without crashing event pipeline', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
        'completed_at' => now(),
    ]);

    ConditionRegistry::register('broken_condition_r8', BrokenConditionForTestR8::class);

    AvatarFrame::create([
        'name' => 'Сломанная рамка R8',
        'type' => 'achievement',
        'condition_class' => 'broken_condition_r8',
        'is_active' => true,
        'price' => 0,
        'image_path' => 'frames/broken.png',
    ]);

    $listener = new \App\Listeners\AchievementListener;

    // Must not throw RuntimeException
    $listener->handle(new OrderCompletedEvent($order));

    expect(true)->toBeTrue();
});
