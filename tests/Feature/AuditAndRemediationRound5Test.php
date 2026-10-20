<?php

use App\Models\Admin;
use App\Models\AdminBroadcast;
use App\Models\ChatBlock;
use App\Models\ContentPack;
use App\Models\ContentPackChangeRequest;
use App\Models\Conversation;
use App\Models\IdolApplication;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceChangeRequest;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function getOrCreateTestCategoryAndUnitR5(): array
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

test('Admin Dashboard opens successfully when broadcast has null admin relation', function () {
    $admin = Admin::create([
        'name' => 'Admin R5',
        'email' => 'admin_r5_'.uniqid().'@test.pw',
        'password' => bcrypt('password'),
    ]);

    // Create broadcast where admin relation returns null (non-existent admin_id)
    DB::statement("SET session_replication_role = 'replica';");
    AdminBroadcast::create([
        'admin_id' => 999999, // Non-existent admin
        'title' => ['ru' => 'Системное оповещение'],
        'body' => ['ru' => 'Текст оповещения'],
        'target' => 'all',
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->has('recent_broadcasts')
        ->where('recent_broadcasts.0.admin.name', 'Система')
    );
});

test('WalletService purchaseContentPack throws DomainException when author is missing', function () {
    $buyer = User::factory()->create();

    DB::statement("SET session_replication_role = 'replica';");
    $pack = ContentPack::create([
        'user_id' => 999999, // Non-existent user
        'title' => 'Ghost Pack',
        'price' => 100,
        'status' => 'published',
        'published_at' => now(),
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $walletService = app(WalletService::class);

    expect(fn () => $walletService->purchaseContentPack($buyer, $pack))
        ->toThrow(\DomainException::class, 'Автор контент-пака недоступен.');
});

test('ReviewController index handles null reviewer safely', function () {
    $idol = User::factory()->create(['is_idol' => true]);

    DB::statement("SET session_replication_role = 'replica';");
    $review = Review::create([
        'reviewer_id' => 999999, // Non-existent reviewer
        'idol_id' => $idol->id,
        'order_id' => 1,
        'rating' => 5,
        'text' => 'Отличная работа!',
        'is_hidden' => false,
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $response = $this->getJson(route('users.reviews', $idol));

    $response->assertOk();
    $data = $response->json();
    expect($data['reviews'])->toHaveCount(1)
        ->and($data['reviews'][0]['reviewer'])->toBeNull();
});

test('Change request moderation returns 404 when parent entity is missing', function () {
    $admin = Admin::create([
        'name' => 'Admin CR',
        'email' => 'admin_cr_'.uniqid().'@test.pw',
        'password' => bcrypt('password'),
    ]);

    DB::statement("SET session_replication_role = 'replica';");
    $serviceCr = ServiceChangeRequest::create([
        'service_id' => 999999, // Missing service
        'changed_fields' => ['price'],
        'pending_price' => 2000,
        'status' => 'pending',
    ]);

    $packCr = ContentPackChangeRequest::create([
        'content_pack_id' => 999999, // Missing pack
        'changed_fields' => ['title'],
        'pending_title' => 'New Title',
        'status' => 'pending',
    ]);
    DB::statement("SET session_replication_role = 'origin';");

    $responseService = $this->actingAs($admin, 'admin')->post(route('admin.services.change-requests.decide', $serviceCr), [
        'decision' => 'approved',
    ]);
    $responseService->assertStatus(404);

    $responsePack = $this->actingAs($admin, 'admin')->post(route('admin.content-packs.change-requests.decide', $packCr), [
        'decision' => 'approved',
    ]);
    $responsePack->assertStatus(404);
});

test('Admin ApplicationController prevents double handling of applications', function () {
    $admin = Admin::create([
        'name' => 'Admin App',
        'email' => 'admin_app_'.uniqid().'@test.pw',
        'password' => bcrypt('password'),
    ]);

    $user = User::factory()->create();
    $app = IdolApplication::create([
        'user_id' => $user->id,
        'face_photo_path' => 'photos/face.jpg',
        'status' => 'approved', // Already approved
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
    ]);

    // Attempting to approve again
    $resApprove = $this->actingAs($admin, 'admin')->patch(route('admin.applications.approve', $app));
    $resApprove->assertSessionHasErrors(['error']);

    // Attempting to reject already approved
    $resReject = $this->actingAs($admin, 'admin')->patch(route('admin.applications.reject', $app), [
        'rejection_reason' => 'Some reason',
    ]);
    $resReject->assertSessionHasErrors(['error']);
});

test('ConversationController block rejects support conversation and empty recipient', function () {
    $user = User::factory()->create();

    // 1. Support conversation
    $supportConv = Conversation::create([
        'is_support' => true,
    ]);
    $supportConv->participants()->create(['user_id' => $user->id]);

    $resSupport = $this->actingAs($user)->postJson("/conversations/{$supportConv->id}/block", [
        'reason' => 'Spam',
    ]);
    $resSupport->assertStatus(422);

    // 2. Normal conversation with no other participant
    $emptyConv = Conversation::create([
        'is_support' => false,
    ]);
    $emptyConv->participants()->create(['user_id' => $user->id]);

    $resEmpty = $this->actingAs($user)->postJson("/conversations/{$emptyConv->id}/block", [
        'reason' => 'Spam',
    ]);
    $resEmpty->assertStatus(422);
});

test('User anonymize clears followers, following, and chat blocks', function () {
    $user = User::factory()->create(['is_idol' => true]);
    $other = User::factory()->create();

    // Create follow relationships
    $user->following()->attach($other->id);
    $other->following()->attach($user->id);

    // Create chat blocks
    ChatBlock::create(['blocker_id' => $user->id, 'blocked_id' => $other->id, 'reason' => 'Block test']);
    ChatBlock::create(['blocker_id' => $other->id, 'blocked_id' => $user->id, 'reason' => 'Block test 2']);

    expect($user->following()->count())->toBe(1)
        ->and($user->followers()->count())->toBe(1)
        ->and(ChatBlock::where('blocker_id', $user->id)->count())->toBe(1)
        ->and(ChatBlock::where('blocked_id', $user->id)->count())->toBe(1);

    $user->anonymize();

    expect($user->fresh()->following()->count())->toBe(0)
        ->and($user->fresh()->followers()->count())->toBe(0)
        ->and(ChatBlock::where('blocker_id', $user->id)->count())->toBe(0)
        ->and(ChatBlock::where('blocked_id', $user->id)->count())->toBe(0);
});

test('ContentPackController and ServiceController destroy enforce delete policy authorization', function () {
    [$category, $timeUnit] = getOrCreateTestCategoryAndUnitR5();
    $owner = User::factory()->create(['is_idol' => true]);
    $stranger = User::factory()->create(['is_idol' => true]);

    $service = Service::create([
        'user_id' => $owner->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Услуга автора'],
        'price' => 500,
        'status' => 'approved',
    ]);

    $pack = ContentPack::create([
        'user_id' => $owner->id,
        'title' => 'Авторский пак',
        'price' => 300,
        'status' => 'approved',
    ]);

    // Stranger cannot delete pack
    $resPack = $this->actingAs($stranger)->delete(route('content-packs.destroy', $pack));
    $resPack->assertForbidden();

    // Stranger cannot delete service
    $resService = $this->actingAs($stranger)->delete(route('profile.services.destroy', $service));
    $resService->assertForbidden();

    // Owner can delete
    $resServiceOwner = $this->actingAs($owner)->delete(route('profile.services.destroy', $service));
    $resServiceOwner->assertRedirect();
    expect($service->fresh()->trashed())->toBeTrue();

    $resPackOwner = $this->actingAs($owner)->delete(route('content-packs.destroy', $pack));
    $resPackOwner->assertRedirect();
    expect(ContentPack::find($pack->id))->toBeNull();
});
