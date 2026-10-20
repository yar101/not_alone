<?php

use App\Models\Admin;
use App\Models\ChatBlock;
use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\IdolApplication;
use App\Models\IdolRatingLog;
use App\Models\Interest;
use App\Models\InterestCategory;
use App\Models\PersonalityTrait;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createTestAdminR9(): Admin
{
    return Admin::create([
        'name' => 'Admin Test R9',
        'email' => 'admin_r9_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

test('RegisteredUserController::store validates name min:2 and max:100', function () {
    // 1-character name must fail min:2
    $responseMin = $this->post('/register', [
        'name' => 'A',
        'gender' => 'male',
        'birth_date' => now()->subYears(20)->toDateString(),
        'email' => 'valid_r9_min@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
    $responseMin->assertSessionHasErrors(['name']);

    // 101-character name must fail max:100
    $responseMax = $this->post('/register', [
        'name' => str_repeat('a', 101),
        'gender' => 'male',
        'birth_date' => now()->subYears(20)->toDateString(),
        'email' => 'valid_r9_max@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
    $responseMax->assertSessionHasErrors(['name']);

    // Valid 5-character name passes
    $responseOk = $this->post('/register', [
        'name' => 'AlexR9',
        'gender' => 'male',
        'birth_date' => now()->subYears(20)->toDateString(),
        'email' => 'valid_r9_ok@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
    $responseOk->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['email' => 'valid_r9_ok@example.com']);
});

test('GalleryController::photos excludes soft-deleted content pack photos in mine mode', function () {
    $idol = User::factory()->create(['is_idol' => true]);

    $activePack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Активный пак',
        'price' => 100,
        'status' => 'published',
    ]);
    $activePhoto = ContentPackPhoto::create([
        'content_pack_id' => $activePack->id,
        'path' => 'content-packs/active.jpg',
    ]);

    $deletedPack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Удаленный пак',
        'price' => 100,
        'status' => 'published',
    ]);
    $deletedPhoto = ContentPackPhoto::create([
        'content_pack_id' => $deletedPack->id,
        'path' => 'content-packs/deleted.jpg',
    ]);
    $deletedPack->delete(); // Soft delete

    $response = $this->actingAs($idol)->getJson('/gallery/photos?mine=1');

    $response->assertOk();
    $photoIds = collect($response->json('photos'))->pluck('id')->all();

    expect($photoIds)->toContain($activePhoto->id)
        ->and($photoIds)->not->toContain($deletedPhoto->id);
});

test('FollowController::toggle blocks following if follower has blocked the idol', function () {
    $follower = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    // Follower has blocked the idol
    ChatBlock::create([
        'blocker_id' => $follower->id,
        'blocked_id' => $idol->id,
        'reason' => 'Блокировка от подписчика',
    ]);

    $response = $this->actingAs($follower)->post("/users/{$idol->id}/follow");
    $response->assertStatus(403);

    expect($follower->isFollowing($idol->id))->toBeFalse();
});

test('Admin ContentPackModerationController bulkApprove and bulkReject are resilient to notification crashes', function () {
    $admin = createTestAdminR9();
    $user = User::factory()->create(['is_idol' => true]);

    $pack1 = ContentPack::create([
        'user_id' => $user->id,
        'title' => 'Пак на апрув',
        'price' => 200,
        'status' => 'pending',
    ]);

    $pack2 = ContentPack::create([
        'user_id' => $user->id,
        'title' => 'Пак на реджект',
        'price' => 200,
        'status' => 'pending',
    ]);
    ContentPackPhoto::create([
        'content_pack_id' => $pack2->id,
        'path' => 'content-packs/test_reject.jpg',
    ]);

    Notification::shouldReceive('send')
        ->andThrow(new \RuntimeException('WebPush failure in bulk moderation'));

    $approveResp = $this->actingAs($admin, 'admin')->post('/admin/content-packs/bulk-approve', [
        'ids' => [$pack1->id],
    ]);
    $approveResp->assertSessionHas('success');
    expect($pack1->fresh()->status)->toBe('approved');

    $rejectResp = $this->actingAs($admin, 'admin')->post('/admin/content-packs/bulk-reject', [
        'ids' => [$pack2->id],
    ]);
    $rejectResp->assertSessionHas('success');
    expect($pack2->fresh()->status)->toBe('rejected');
});

test('Admin ServiceModerationController bulkApprove and bulkReject are resilient to notification crashes', function () {
    $admin = createTestAdminR9();
    $user = User::factory()->create(['is_idol' => true]);
    $category = ServiceCategory::first() ?? ServiceCategory::create(['name' => ['ru' => 'Кат R9']]);
    $timeUnit = ServiceTimeUnit::first() ?? ServiceTimeUnit::create(['name' => ['ru' => 'час']]);

    $service1 = Service::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Услуга 1',
        'price' => 300,
        'status' => 'pending',
        'is_active' => true,
    ]);

    $service2 = Service::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => 'Услуга 2',
        'price' => 400,
        'status' => 'pending',
        'is_active' => true,
    ]);

    Notification::shouldReceive('send')
        ->andThrow(new \RuntimeException('WebPush failure in service moderation'));

    $approveResp = $this->actingAs($admin, 'admin')->post('/admin/services/bulk-approve', [
        'ids' => [$service1->id],
    ]);
    $approveResp->assertSessionHas('success');
    expect($service1->fresh()->status)->toBe('approved');

    $rejectResp = $this->actingAs($admin, 'admin')->post('/admin/services/bulk-reject', [
        'ids' => [$service2->id],
        'rejection_reason' => 'Не соответствует правилам',
    ]);
    $rejectResp->assertSessionHas('success');
    expect($service2->fresh()->status)->toBe('rejected');
});

test('Admin ApplicationController approve and reject succeed even if notification throws', function () {
    $admin = createTestAdminR9();
    $user1 = User::factory()->create(['is_idol' => false]);
    $user2 = User::factory()->create(['is_idol' => false]);

    $app1 = IdolApplication::create([
        'user_id' => $user1->id,
        'face_photo_path' => 'photos/face1.jpg',
        'status' => 'pending',
    ]);

    $app2 = IdolApplication::create([
        'user_id' => $user2->id,
        'face_photo_path' => 'photos/face2.jpg',
        'status' => 'pending',
    ]);

    Notification::shouldReceive('send')
        ->andThrow(new \RuntimeException('Notification failure'));

    $respApprove = $this->actingAs($admin, 'admin')->patch("/admin/applications/{$app1->id}/approve");
    $respApprove->assertSessionHas('success');
    expect($app1->fresh()->status)->toBe('approved')
        ->and($user1->fresh()->is_idol)->toBeTrue();

    $respReject = $this->actingAs($admin, 'admin')->patch("/admin/applications/{$app2->id}/reject", [
        'rejection_reason' => 'Недостаточно опыта',
    ]);
    $respReject->assertSessionHas('success');
    expect($app2->fresh()->status)->toBe('rejected');
});

test('Dictionary controllers forget English translation when empty value is sent', function () {
    $admin = createTestAdminR9();

    // 1. PersonalityTrait
    $trait = PersonalityTrait::create([
        'name' => ['ru' => 'Доброта', 'en' => 'Kindness'],
        'sort_order' => 1,
    ]);
    $this->actingAs($admin, 'admin')->patch("/admin/traits/{$trait->id}", [
        'name_ru' => 'Доброта',
        'name_en' => '',
    ]);
    expect($trait->fresh()->hasTranslation('name', 'en'))->toBeFalse();

    // 2. InterestCategory
    $interestCat = InterestCategory::create([
        'name' => ['ru' => 'Игры', 'en' => 'Gaming'],
        'sort_order' => 1,
    ]);
    $this->actingAs($admin, 'admin')->patch("/admin/interests/categories/{$interestCat->id}", [
        'name_ru' => 'Игры',
        'name_en' => '',
    ]);
    expect($interestCat->fresh()->hasTranslation('name', 'en'))->toBeFalse();

    // 3. Interest
    $interest = Interest::create([
        'category_id' => $interestCat->id,
        'name' => ['ru' => 'Шахматы', 'en' => 'Chess'],
        'sort_order' => 1,
    ]);
    $this->actingAs($admin, 'admin')->patch("/admin/interests/{$interest->id}", [
        'category_id' => $interestCat->id,
        'name_ru' => 'Шахматы',
        'name_en' => '',
    ]);
    expect($interest->fresh()->hasTranslation('name', 'en'))->toBeFalse();

    // 4. ServiceCategory
    $serviceCat = ServiceCategory::create([
        'name' => ['ru' => 'Общение', 'en' => 'Chatting'],
        'description' => ['ru' => 'Описание', 'en' => 'Description'],
    ]);
    $this->actingAs($admin, 'admin')->patch("/admin/services/categories/{$serviceCat->id}", [
        'name_ru' => 'Общение',
        'name_en' => '',
        'description_ru' => 'Описание',
        'description_en' => '',
    ]);
    expect($serviceCat->fresh()->hasTranslation('name', 'en'))->toBeFalse()
        ->and($serviceCat->fresh()->hasTranslation('description', 'en'))->toBeFalse();

    // 5. ServiceTimeUnit
    $timeUnit = ServiceTimeUnit::create([
        'name' => ['ru' => 'день', 'en' => 'day'],
    ]);
    $this->actingAs($admin, 'admin')->patch("/admin/services/time-units/{$timeUnit->id}", [
        'name_ru' => 'день',
        'name_en' => '',
    ]);
    expect($timeUnit->fresh()->hasTranslation('name', 'en'))->toBeFalse();
});

test('ChatBlock and IdolRatingLog load soft-deleted users cleanly', function () {
    $user1 = User::factory()->create(['name' => 'User Blocker']);
    $user2 = User::factory()->create(['name' => 'User Blocked']);

    $block = ChatBlock::create([
        'blocker_id' => $user1->id,
        'blocked_id' => $user2->id,
        'reason' => 'Test reason',
    ]);

    $ratingLog = IdolRatingLog::create([
        'user_id' => $user1->id,
        'event' => 'admin_manual',
        'delta' => 1.5,
        'created_at' => now(),
    ]);

    // Soft-delete user1 and user2
    $user1->delete();
    $user2->delete();

    $block->refresh();
    expect($block->blocker)->not->toBeNull()
        ->and($block->blocker->id)->toBe($user1->id)
        ->and($block->blocked)->not->toBeNull()
        ->and($block->blocked->id)->toBe($user2->id);

    $ratingLog->refresh();
    expect($ratingLog->user)->not->toBeNull()
        ->and($ratingLog->user->id)->toBe($user1->id);
});
