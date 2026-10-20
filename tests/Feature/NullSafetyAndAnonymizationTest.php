<?php

use App\Enums\OrderStatus;
use App\Events\OrderCompletedEvent;
use App\Listeners\AchievementListener;
use App\Models\Admin;
use App\Models\AvatarFrame;
use App\Models\ContentPack;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Models\UserStrike;
use App\Notifications\AdminRatingNotification;
use App\Notifications\IdolPayoutReleasedNotification;
use App\Notifications\LowRatingWarningNotification;
use App\Notifications\UserStrikeNotification;
use App\Services\IdolRatingService;
use App\Services\OrderService;
use App\Services\UserProfileService;
use App\Traits\NotificationTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function getOrCreateTestCategoryAndUnit(): array
{
    $category = ServiceCategory::first();
    if (! $category) {
        $category = ServiceCategory::create([
            'name' => ['ru' => 'Гейминг', 'en' => 'Gaming'],
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

test('IdolRatingService adjust works with admin_manual and threshold crossing without DB import errors', function () {
    Notification::fake();

    $idol = User::factory()->create([
        'is_idol' => true,
        'rating' => 35,
    ]);

    PlatformSetting::set('rating_low_threshold', 30);

    // 1. Manual adjustment by admin (positive delta dampened: 5 * (100 - 35)/80 = 4.06)
    IdolRatingService::adjust($idol, 'admin_manual', 5.0, 'Manual admin boost');

    expect($idol->fresh()->rating)->toBe(39.06);
    Notification::assertSentTo($idol, AdminRatingNotification::class);

    // 2. Drop below threshold (negative delta: 39.06 - 15 = 24.06)
    IdolRatingService::adjust($idol, 'admin_manual', -15.0, 'Penalty drop below 30');

    expect($idol->fresh()->rating)->toBe(24.06);
    Notification::assertSentTo($idol, LowRatingWarningNotification::class);
});

test('UserProfileService getCategoryIdols excludes soft-deleted and banned users without null errors', function () {
    [$category, $timeUnit] = getOrCreateTestCategoryAndUnit();
    $viewer = User::factory()->create();

    // Active idol
    $activeIdol = User::factory()->create([
        'is_idol' => true,
        'is_banned' => false,
        'name' => 'Active Idol',
    ]);
    Service::create([
        'user_id' => $activeIdol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга 1'],
        'price' => 1000,
        'is_active' => true,
        'status' => 'approved',
    ]);

    // Banned idol
    $bannedIdol = User::factory()->create([
        'is_idol' => true,
        'is_banned' => true,
        'name' => 'Banned Idol',
    ]);
    Service::create([
        'user_id' => $bannedIdol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга бана'],
        'price' => 1000,
        'is_active' => true,
        'status' => 'approved',
    ]);

    // Soft-deleted idol
    $deletedIdol = User::factory()->create([
        'is_idol' => true,
        'name' => 'Deleted Idol',
    ]);
    Service::create([
        'user_id' => $deletedIdol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга удаленного'],
        'price' => 1000,
        'is_active' => true,
        'status' => 'approved',
    ]);
    $deletedIdol->delete(); // soft delete

    $service = new UserProfileService;
    $request = Request::create('/', 'GET', ['page' => 1, 'per_page' => 10]);
    $request->setLaravelSession(app('session.store'));

    $result = $service->getCategoryIdols($viewer, $category, $request);

    expect($result['idols'])->toHaveCount(1)
        ->and($result['idols'][0]['id'])->toBe($activeIdol->id);
});

test('User anonymize deactivates services, hides content packs, and clears frames', function () {
    [$category, $timeUnit] = getOrCreateTestCategoryAndUnit();

    $user = User::factory()->create([
        'is_idol' => true,
    ]);

    $frame = AvatarFrame::create([
        'name' => 'Test Frame',
        'image_path' => 'frames/test.png',
        'type' => 'free',
        'is_active' => true,
    ]);

    $user->avatarFrames()->attach($frame->id);
    $user->update(['active_frame_id' => $frame->id]);

    $service = Service::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Тест услуга'],
        'price' => 500,
        'is_active' => true,
        'status' => 'approved',
    ]);

    $pack = ContentPack::create([
        'user_id' => $user->id,
        'title' => 'Test Pack',
        'price' => 200,
        'status' => 'approved',
        'published_at' => now(),
        'hidden_at' => null,
    ]);

    $user->anonymize();
    $user->refresh();

    expect($user->name)->toBe('Удалённый пользователь')
        ->and($user->active_frame_id)->toBeNull()
        ->and($user->avatarFrames()->count())->toBe(0)
        ->and($service->fresh()->is_active)->toBeFalse()
        ->and($pack->fresh()->hidden_at)->not->toBeNull();
});

test('Order formatOrder handles null customer and idol safely', function () {
    $order = new Order([
        'status' => OrderStatus::Completed,
        'customer_id' => 999999,
        'idol_id' => 888888,
    ]);
    $order->id = 123;
    $order->created_at = now();
    $order->setRelation('items', collect());
    $order->setRelation('customer', null);
    $order->setRelation('idol', null);

    $orderService = app(OrderService::class);
    $formatted = $orderService->formatOrder($order, false);

    expect($formatted['id'])->toBe(123)
        ->and($formatted['customer'])->toBeNull()
        ->and($formatted['idol'])->toBeNull();
});

test('OrderController disputable handles missing idol without 500', function () {
    $customer = User::factory()->create();

    DB::statement("SET session_replication_role = 'replica';");
    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => 999999, // Non-existent user
        'status' => OrderStatus::Completed,
        'completed_at' => now()->subMinutes(10),
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $response = $this->actingAs($customer)->getJson('/orders/disputable');

    $response->assertOk();
    $data = $response->json();
    expect($data)->toHaveCount(1)
        ->and($data[0]['idol_name'])->toBe('Пользователь');
});

test('Admin Service and ContentPack moderation show handles deleted user without 500', function () {
    [$category, $timeUnit] = getOrCreateTestCategoryAndUnit();
    $admin = Admin::create([
        'name' => 'Admin Test',
        'email' => 'admin_test_'.uniqid().'@test.pw',
        'password' => bcrypt('password'),
    ]);

    DB::statement("SET session_replication_role = 'replica';");
    $service = Service::create([
        'user_id' => 999999, // Non-existent user
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга'],
        'price' => 500,
        'status' => 'pending',
    ]);
    $pack = ContentPack::create([
        'user_id' => 999999, // Non-existent user
        'title' => 'Pack',
        'price' => 300,
        'status' => 'pending_review',
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $resService = $this->actingAs($admin, 'admin')->get(route('admin.services.moderation.show', $service));
    $resService->assertOk();

    $resPack = $this->actingAs($admin, 'admin')->get(route('admin.content-packs.show', $pack));
    $resPack->assertOk();
});

test('AchievementListener handles null users and repeated executions idempotently', function () {
    $order = new Order;
    $order->id = 1;
    $order->setRelation('customer', null);
    $order->setRelation('idol', null);

    $listener = new AchievementListener;
    // Should not throw TypeError when idol/customer are null
    $listener->handle(new OrderCompletedEvent($order));

    // When idol exists and condition passes
    $user = User::factory()->create();
    $frame = AvatarFrame::create([
        'name' => 'Achievement Frame',
        'image_path' => 'frames/ach.png',
        'type' => 'achievement',
        'condition_class' => null,
        'is_active' => true,
    ]);

    // Test idempotent syncWithoutDetaching
    $user->avatarFrames()->syncWithoutDetaching([$frame->id]);
    expect($user->avatarFrames()->count())->toBe(1);

    // Second sync should not throw unique constraint violation
    $user->avatarFrames()->syncWithoutDetaching([$frame->id]);
    expect($user->avatarFrames()->count())->toBe(1);
});

test('NotificationTypes includes idol_payout_released and strike in SERVICE_TYPES and TYPE_MAP', function () {
    $dummy = new class
    {
        use NotificationTypes;

        public function getServiceTypes(): array
        {
            return $this->SERVICE_TYPES;
        }

        public function getTypeMap(): array
        {
            return $this->TYPE_MAP;
        }

        public function classesFor(array $types): array
        {
            return $this->getClassesForTypes($types);
        }
    };

    expect($dummy->getServiceTypes())->toContain('idol_payout_released')
        ->and($dummy->getServiceTypes())->toContain('strike')
        ->and($dummy->getTypeMap()['idol_payout_released'])->toBe(IdolPayoutReleasedNotification::class)
        ->and($dummy->getTypeMap()['strike'])->toBe(UserStrikeNotification::class);

    $serviceClasses = $dummy->classesFor($dummy->getServiceTypes());
    expect($serviceClasses)->toContain(IdolPayoutReleasedNotification::class)
        ->and($serviceClasses)->toContain(UserStrikeNotification::class);

    $strike = UserStrike::create([
        'user_id' => User::factory()->create()->id,
        'admin_id' => Admin::create([
            'name' => 'Admin',
            'email' => 'admin_strike_'.uniqid().'@test.pw',
            'password' => bcrypt('password'),
        ])->id,
        'rating_deducted' => -5,
        'admin_note' => 'Strike note',
        'expires_at' => now()->addMonths(6),
    ]);

    $notif = new UserStrikeNotification($strike);
    $array = $notif->toArray($strike->user);
    expect($array['type'])->toBe('strike')
        ->and($array['strike_id'])->toBe($strike->id);
});

test('ConversationController store rejects idol messaging themselves', function () {
    $idol = User::factory()->create(['is_idol' => true]);

    $response = $this->actingAs($idol)->postJson('/conversations', [
        'target_user_id' => $idol->id,
    ]);

    $response->assertStatus(422);
});

test('AvatarFrameController equip rejects inactive avatar frame', function () {
    $user = User::factory()->create();

    $frame = AvatarFrame::create([
        'name' => 'Inactive Frame',
        'image_path' => 'frames/inactive.png',
        'type' => 'free',
        'is_active' => false,
    ]);

    $response = $this->actingAs($user)->postJson(route('avatar-frames.equip', $frame));

    $response->assertStatus(422)
        ->assertJson(['message' => 'Эта рамка временно недоступна.']);
});
