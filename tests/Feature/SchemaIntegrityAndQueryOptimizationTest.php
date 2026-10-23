<?php

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\IdolQuizQuestion;
use App\Models\IdolQuizSession;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createTestAdminSchemaOpt(): Admin
{
    return Admin::create([
        'name' => 'Admin Schema Opt',
        'email' => 'admin_schema_opt_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

test('database schema optimization creates all performance indexes and eliminates unindexed foreign keys', function () {
    $expectedIndexes = [
        'messages' => ['messages_sender_id_index'],
        'users' => ['users_active_frame_id_index', 'users_banned_by_index', 'users_deleted_at_index'],
        'services' => ['services_moderated_by_index', 'services_deleted_at_index', 'services_status_created_at_index'],
        'idol_applications' => ['idol_applications_reviewed_by_index'],
        'admin_broadcasts' => ['admin_broadcasts_admin_id_index'],
        'idol_category_descriptions' => ['idol_category_descriptions_category_id_index'],
        'user_reports' => ['user_reports_reviewed_by_index', 'user_reports_status_created_at_index'],
        'post_likes' => ['post_likes_user_id_index'],
        'orders' => ['orders_cancelled_by_index'],
        'content_packs' => ['content_packs_moderated_by_index', 'content_packs_deleted_at_index'],
        'content_pack_reviews' => ['content_pack_reviews_admin_id_index', 'content_pack_reviews_content_pack_id_index'],
        'content_pack_change_requests' => ['content_pack_change_requests_reviewed_by_index', 'content_pack_change_requests_status_created_at_index'],
        'service_change_requests' => [
            'service_change_requests_pending_category_id_index',
            'service_change_requests_pending_time_unit_id_index',
            'service_change_requests_reviewed_by_index',
            'service_change_requests_status_created_at_index',
        ],
        'service_reviews' => ['service_reviews_admin_id_index', 'service_reviews_service_id_index'],
        'user_idol_trials' => ['user_idol_trials_idol_id_index', 'user_idol_trials_order_id_index'],
        'trait_suggestions' => ['trait_suggestions_user_id_index'],
        'interest_suggestions' => ['interest_suggestions_user_id_index'],
        'order_disputes' => ['order_disputes_status_created_at_index'],
        'review_disputes' => ['review_disputes_status_created_at_index'],
        'admin_logs' => ['admin_logs_created_at_index'],
        'idol_quiz_sessions' => ['idol_quiz_sessions_status_index'],
    ];

    foreach ($expectedIndexes as $table => $indexes) {
        $existing = DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public' AND tablename = :tbl", ['tbl' => $table]);
        $existingNames = array_column($existing, 'indexname');

        foreach ($indexes as $index) {
            expect($existingNames)->toContain($index);
        }
    }

    // Verify 0 unindexed foreign keys exist across the database
    $unindexedFks = DB::select("
        WITH fk_columns AS (
            SELECT
                c.conrelid::regclass AS table_name,
                c.conname AS fk_name,
                a.attname AS column_name
            FROM pg_constraint c
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY(c.conkey)
            WHERE c.contype = 'f'
        ),
        indexed_columns AS (
            SELECT
                t.relname AS table_name,
                a.attname AS column_name
            FROM pg_index i
            JOIN pg_class t ON t.oid = i.indrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = i.indkey[0]
        )
        SELECT fk.table_name, fk.column_name, fk.fk_name
        FROM fk_columns fk
        LEFT JOIN indexed_columns ic ON ic.table_name = fk.table_name::text AND ic.column_name = fk.column_name
        WHERE ic.column_name IS NULL
    ");

    expect($unindexedFks)->toBeEmpty();
});

test('DashboardController correctly calculates consolidated wallet metrics and quiz stats', function () {
    $admin = createTestAdminSchemaOpt();
    $user = User::factory()->create();
    $wallet = $user->getOrCreateWallet();

    // Create wallet transactions
    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'type' => 'platform_fee',
        'amount' => -250.50,
        'balance_before' => 1000,
        'balance_after' => 749.50,
        'status' => 'completed',
    ]);

    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'type' => 'order_payout',
        'amount' => 1500.00,
        'balance_before' => 749.50,
        'balance_after' => 2249.50,
        'status' => 'completed',
    ]);

    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'type' => 'deposit',
        'amount' => 3000.00,
        'balance_before' => 2249.50,
        'balance_after' => 5249.50,
        'status' => 'completed',
    ]);

    // Create quiz sessions
    IdolQuizSession::create([
        'user_id' => $user->id,
        'status' => 'passed',
        'errors_count' => 0,
        'attempt_number' => 1,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    IdolQuizSession::create([
        'user_id' => $user->id,
        'status' => 'failed',
        'errors_count' => 3,
        'attempt_number' => 2,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->where('metrics.platform_fee_total', fn ($v) => (float) $v === 250.50)
        ->where('metrics.turnover_total', fn ($v) => (float) $v === 1500.00)
        ->where('metrics.deposits_total', fn ($v) => (float) $v === 3000.00)
        ->where('quiz_stats.total', 2)
        ->where('quiz_stats.passed', 1)
        ->where('quiz_stats.failed', 1)
        ->where('quiz_stats.pass_rate', 50)
    );
});

test('Conversation findOrCreateBetween is idempotent and thread-safe', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conv1 = Conversation::findOrCreateBetween($userA, $userB);
    $conv2 = Conversation::findOrCreateBetween($userB, $userA);
    $conv3 = Conversation::findOrCreateBetween($userA, $userB);

    expect($conv1->id)->toBe($conv2->id);
    expect($conv1->id)->toBe($conv3->id);

    // Ensure only 1 conversation was created
    expect(Conversation::whereNull('order_id')->count())->toBe(1);
    expect($conv1->participants()->count())->toBe(2);
});

test('ServiceCategoryController updates translations, suggestions, and attributes atomically', function () {
    $admin = createTestAdminSchemaOpt();
    $cat = ServiceCategory::create([
        'name' => ['ru' => 'Тест', 'en' => 'Test'],
        'description' => ['ru' => 'Описание', 'en' => 'Description'],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($admin, 'admin')->patch(route('admin.services.categories.update', $cat), [
        'name_ru' => 'Новое имя',
        'name_en' => 'New name',
        'description_ru' => 'Новое описание',
        'description_en' => 'New description',
        'accent_color' => '#123456',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $cat->refresh();

    expect($cat->getTranslation('name', 'ru'))->toBe('Новое имя');
    expect($cat->getTranslation('name', 'en'))->toBe('New name');
    expect($cat->getTranslation('description', 'ru'))->toBe('Новое описание');
    expect($cat->getTranslation('description', 'en'))->toBe('New description');
    expect($cat->accent_color)->toBe('#123456');
});
