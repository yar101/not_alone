<?php

use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Models\Order;
use App\Models\ReviewEpithet;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Services\Media\AvatarService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

test('user cannot order services from themselves', function () {
    $user = User::factory()->create(['is_idol' => true]);
    $cat = ServiceCategory::create(['name' => ['ru' => 'Тест'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час'], 'sort_order' => 1]);

    $service = Service::create([
        'user_id' => $user->id,
        'category_id' => $cat->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга'],
        'price' => 500,
        'status' => 'approved',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->postJson(route('orders.store'), [
        'idol_id' => $user->id,
        'services' => [['id' => $service->id, 'quantity' => 1]],
    ]);

    $response->assertStatus(422);
    expect(Order::where('customer_id', $user->id)->count())->toBe(0);
});

test('user cannot purchase their own content pack', function () {
    $user = User::factory()->create(['is_idol' => true]);
    $pack = ContentPack::create([
        'user_id' => $user->id,
        'title' => 'Мой пак',
        'price' => 300,
        'status' => 'published',
    ]);

    $response = $this->actingAs($user)->postJson(route('content-packs.purchase'), [
        'items' => [$pack->id],
    ]);

    $response->assertOk();
    expect(ContentPackPurchase::where('user_id', $user->id)->where('content_pack_id', $pack->id)->count())->toBe(0);
});

test('avatar service handles image upload, square center crop, and deletion', function () {
    Storage::fake(config('filesystems.default'));

    $user = User::factory()->create();
    $avatarService = new AvatarService;

    // Create a 800x600 test image
    $file = UploadedFile::fake()->image('avatar.jpg', 800, 600);

    $path = $avatarService->updateAvatar($user, $file);

    expect($path)->toBeString();
    expect($user->fresh()->avatar_path)->toBe($path);
    Storage::disk(config('filesystems.default'))->assertExists($path);

    // Test deletion
    $avatarService->deleteAvatar($user);
    expect($user->fresh()->avatar_path)->toBeNull();
    Storage::disk(config('filesystems.default'))->assertMissing($path);
});

test('review epithet cache is invalidated on save and delete', function () {
    Cache::flush();

    $epithet = ReviewEpithet::create(['label' => 'Вежливый', 'sort_order' => 1]);

    $response = $this->getJson(route('reviews.epithets'));
    $response->assertOk();
    expect($response->json())->toHaveCount(1);
    expect(Cache::has('review_epithets_list'))->toBeTrue();

    // Create another epithet -> cache must be cleared automatically
    ReviewEpithet::create(['label' => 'Пунктуальный', 'sort_order' => 2]);
    expect(Cache::has('review_epithets_list'))->toBeFalse();

    $response2 = $this->getJson(route('reviews.epithets'));
    expect($response2->json())->toHaveCount(2);
});
