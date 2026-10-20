<?php

use App\Models\Admin;
use App\Models\ContentPack;
use App\Models\ContentPackChangeRequest;
use App\Models\IdolApplication;
use App\Models\Interest;
use App\Models\InterestCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceChangeRequest;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createTestAdminOpt(): Admin
{
    return Admin::create([
        'name' => 'Admin Optimization',
        'email' => 'admin_opt_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

test('Database optimization migration creates foreign key and performance indexes', function () {
    $expectedIndexes = [
        'order_items' => ['order_items_order_id_index', 'order_items_service_id_index'],
        'posts' => ['posts_user_id_created_at_index'],
        'content_pack_photos' => ['content_pack_photos_content_pack_id_sort_order_index'],
        'follows' => ['follows_idol_id_index'],
        'services' => ['services_user_id_status_index', 'services_time_unit_id_index'],
        'conversations' => ['conversations_order_id_index', 'conversations_is_support_updated_at_index'],
        'orders' => ['orders_conversation_id_index', 'orders_customer_id_idol_id_index'],
        'order_disputes' => ['order_disputes_order_id_index', 'order_disputes_customer_id_status_index'],
        'review_disputes' => ['review_disputes_review_id_index', 'review_disputes_idol_id_status_index'],
        'user_strikes' => ['user_strikes_user_id_expires_at_index'],
        'user_reports' => ['user_reports_reported_id_status_index', 'user_reports_reporter_id_index'],
        'idol_rating_logs' => ['idol_rating_logs_user_id_created_at_index'],
        'admin_logs' => ['admin_logs_admin_id_created_at_index', 'admin_logs_target_type_target_id_index', 'admin_logs_action_created_at_index'],
        'notifications' => ['notifications_notifiable_created_idx', 'notifications_notifiable_read_idx'],
        'users' => ['users_created_at_index', 'users_idol_quiz_cooldown_until_index'],
        'admin_broadcasts' => ['admin_broadcasts_target_created_at_index', 'admin_broadcasts_target_user_id_index'],
        'help_articles' => ['help_articles_category_id_sort_order_index'],
        'content_pack_change_requests' => ['content_pack_change_requests_content_pack_id_status_index'],
        'service_change_requests' => ['service_change_requests_service_id_status_index'],
    ];

    foreach ($expectedIndexes as $table => $indexes) {
        $existing = DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public' AND tablename = :tbl", ['tbl' => $table]);
        $existingNames = array_column($existing, 'indexname');

        foreach ($indexes as $index) {
            expect($existingNames)->toContain($index);
        }
    }
});

test('HandleInertiaRequests eager loads idolApplication to avoid lazy queries', function () {
    $user = User::factory()->create(['is_idol' => false]);
    IdolApplication::create([
        'user_id' => $user->id,
        'face_photo_path' => 'photos/face.jpg',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get('/');
    $response->assertOk();

    // Check shared inertia auth props
    $response->assertInertia(fn (Assert $page) => $page
        ->where('idol_status', 'pending')
    );
    expect($user->relationLoaded('idolApplication'))->toBeTrue();
});

test('ContentPackModerationController correctly filters pending_review vs resubmitted', function () {
    $admin = createTestAdminOpt();
    $idol = User::factory()->create(['is_idol' => true]);

    $freshPack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Fresh Pack',
        'description' => 'Fresh Pack Description',
        'price' => 500,
        'status' => 'pending_review',
        'resubmitted_at' => null,
    ]);

    $resubmittedPack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Resubmitted Pack',
        'description' => 'Resubmitted Pack Description',
        'price' => 600,
        'status' => 'pending_review',
        'resubmitted_at' => now(),
    ]);

    // 1. Filter pending_review (should only include fresh pack)
    $respPending = $this->actingAs($admin, 'admin')->get('/admin/content-packs?status=pending_review');
    $respPending->assertInertia(fn (Assert $page) => $page
        ->has('packs.data', 1)
        ->where('packs.data.0.id', $freshPack->id)
        ->where('counts.pending_review', 1)
        ->where('counts.resubmitted', 1)
    );

    // 2. Filter resubmitted (should only include resubmitted pack)
    $respResubmitted = $this->actingAs($admin, 'admin')->get('/admin/content-packs?status=resubmitted');
    $respResubmitted->assertInertia(fn (Assert $page) => $page
        ->has('packs.data', 1)
        ->where('packs.data.0.id', $resubmittedPack->id)
    );
});

test('ContentPackChangeRequestController::show handles soft-deleted user and pack cleanly', function () {
    $admin = createTestAdminOpt();
    $idol = User::factory()->create(['is_idol' => true]);

    $pack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Original Pack',
        'description' => 'Original Description',
        'price' => 500,
        'status' => 'has_remarks',
    ]);

    $cr = ContentPackChangeRequest::create([
        'content_pack_id' => $pack->id,
        'changed_fields' => ['title'],
        'pending_title' => 'Updated Pack Title',
        'status' => 'pending',
    ]);

    // Soft-delete user and pack
    $idol->delete();
    $pack->delete();

    $resp = $this->actingAs($admin, 'admin')->get("/admin/content-packs/change-requests/{$cr->id}");
    $resp->assertOk();
    $resp->assertInertia(fn (Assert $page) => $page
        ->where('changeRequest.id', $cr->id)
        ->where('pack.id', $pack->id)
        ->where('pack.user.id', $idol->id)
    );
});

test('ServiceChangeRequestController::show handles soft-deleted user and service cleanly', function () {
    $admin = createTestAdminOpt();
    $idol = User::factory()->create(['is_idol' => true]);

    $category = ServiceCategory::create(['name' => ['ru' => 'Игры']]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час']]);

    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Оригинальная услуга'],
        'price' => 1000,
        'status' => 'has_remarks',
        'is_active' => true,
    ]);

    $cr = ServiceChangeRequest::create([
        'service_id' => $service->id,
        'changed_fields' => ['name'],
        'pending_name' => ['ru' => 'Новое имя услуги'],
        'status' => 'pending',
    ]);

    // Soft-delete user and service
    $idol->delete();
    $service->delete();

    $resp = $this->actingAs($admin, 'admin')->get("/admin/services/change-requests/{$cr->id}");
    $resp->assertOk();
    $resp->assertInertia(fn (Assert $page) => $page
        ->where('changeRequest.id', $cr->id)
        ->where('service.id', $service->id)
        ->where('service.user.id', $idol->id)
    );
});

test('UserProfileController::show handles user interests with null category safely', function () {
    $user = User::factory()->create();
    $category = InterestCategory::create(['name' => ['ru' => 'Развлечения']]);
    $interest = Interest::create([
        'category_id' => $category->id,
        'name' => ['ru' => 'Музыка'],
    ]);

    $user->interests()->attach($interest->id);

    // Delete category forcefully via DB to simulate orphaned interest
    DB::table('interests')->where('id', $interest->id)->update(['category_id' => $category->id]);

    $resp = $this->get(route('profile.show', $user));
    $resp->assertOk();
});
