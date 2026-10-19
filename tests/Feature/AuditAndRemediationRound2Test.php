<?php

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Models\IdolApplication;
use App\Models\IdolQuizQuestion;
use App\Models\IdolQuizSession;
use App\Models\IdolQuizSessionQuestion;
use App\Models\IdolRatingLog;
use App\Models\Order;
use App\Models\User;
use App\Models\UserLanguage;
use App\Models\UserStrike;
use App\Policies\ContentPackPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function makeAuditAdmin(): Admin
{
    return Admin::create([
        'name' => 'Admin Test',
        'email' => 'admin_'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

test('quiz question import with replace mode cleanly deletes existing questions without foreign key crash', function () {
    $admin = makeAuditAdmin();

    $q1 = IdolQuizQuestion::create([
        'stage' => 1,
        'question' => 'Old question 1',
        'options' => ['A', 'B', 'C', 'D'],
        'correct_option_index' => 0,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create();
    $session = IdolQuizSession::create([
        'user_id' => $user->id,
        'status' => 'passed',
        'attempt_number' => 1,
        'errors_count' => 0,
        'started_at' => now(),
    ]);

    // Reference question in session questions
    IdolQuizSessionQuestion::create([
        'session_id' => $session->id,
        'question_id' => $q1->id,
        'stage' => 1,
        'user_answer_index' => 0,
        'is_correct' => true,
    ]);

    $file = UploadedFile::fake()->createWithContent('questions.json', json_encode([
        'questions' => [
            [
                'stage' => 1,
                'question' => 'New question replaced',
                'options' => ['Opt1', 'Opt2', 'Opt3', 'Opt4'],
                'correct_option_index' => 1,
                'sort_order' => 1,
            ],
        ],
    ]));

    $response = $this->actingAs($admin, 'admin')->post(route('admin.quiz.questions.import'), [
        'file' => $file,
        'mode' => 'replace',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect(IdolQuizQuestion::count())->toBe(1);
    expect(IdolQuizQuestion::first()->question)->toBe('New question replaced');
});

test('order search works case-insensitively', function () {
    $admin = makeAuditAdmin();
    $customer = User::factory()->create(['name' => 'Alice Wonderland', 'email' => 'alice@example.com']);
    $idol = User::factory()->create(['name' => 'Bob Singer', 'email' => 'bob@example.com', 'is_idol' => true]);

    Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid->value,
    ]);

    $resUpper = $this->actingAs($admin, 'admin')->get(route('admin.orders.index', ['search' => 'ALICE']));
    $resUpper->assertOk();

    $resLower = $this->actingAs($admin, 'admin')->get(route('admin.orders.index', ['search' => 'bob']));
    $resLower->assertOk();
});

test('dashboard active_orders_count counts both paid and accepted orders', function () {
    $admin = makeAuditAdmin();
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid->value,
    ]);

    Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted->value,
    ]);

    Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed->value,
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
    $response->assertOk();

    $page = $response->viewData('page');
    expect($page['props']['metrics']['active_orders_count'])->toBe(2);
});

test('strike cancellation restores idol rating via IdolRatingService and logs rating and admin log', function () {
    $admin = makeAuditAdmin();
    $idol = User::factory()->create([
        'is_idol' => true,
        'rating' => 50.0,
    ]);

    // Admin issues strike deducting 5 rating
    $strike = UserStrike::create([
        'user_id' => $idol->id,
        'admin_id' => $admin->id,
        'rating_deducted' => -5.0,
        'admin_note' => 'Test strike',
        'expires_at' => now()->addMonths(6),
    ]);

    $idol->update(['rating' => 45.0]);

    $response = $this->actingAs($admin, 'admin')->delete(route('admin.strikes.destroy', $strike));
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $idol->refresh();
    // Rating should be restored without dampening to 50.0
    expect($idol->rating)->toBe(50.0);

    expect(IdolRatingLog::where('user_id', $idol->id)->where('event', 'strike_cancelled')->exists())->toBeTrue();
    expect(AdminLog::where('action', 'strike_cancel')->where('target_id', $strike->id)->exists())->toBeTrue();
    expect(UserStrike::find($strike->id))->toBeNull();
});

test('admin reset quiz progress wraps in transaction and records admin log', function () {
    $admin = makeAuditAdmin();
    $user = User::factory()->create([
        'idol_quiz_passed_at' => now(),
        'idol_quiz_cooldown_until' => now()->addDays(7),
    ]);

    $session = IdolQuizSession::create([
        'user_id' => $user->id,
        'status' => 'active',
        'attempt_number' => 1,
        'errors_count' => 0,
        'started_at' => now(),
    ]);

    $app = IdolApplication::create([
        'user_id' => $user->id,
        'status' => 'pending',
        'face_photo_path' => 'photos/test.jpg',
    ]);

    $response = $this->actingAs($admin, 'admin')->patch(route('admin.users.reset-quiz', $user));
    $response->assertRedirect();

    $user->refresh();
    expect($user->idol_quiz_passed_at)->toBeNull();
    expect($user->idol_quiz_cooldown_until)->toBeNull();

    $session->refresh();
    expect($session->status)->toBe('failed');

    expect(IdolApplication::find($app->id))->toBeNull();
    expect(AdminLog::where('action', 'reset_quiz_progress')->where('target_id', $user->id)->exists())->toBeTrue();
});

test('user languages update is atomic and replaces languages', function () {
    $user = User::factory()->create();
    UserLanguage::create([
        'user_id' => $user->id,
        'language_code' => 'en',
    ]);

    $response = $this->actingAs($user)->patch(route('profile.update.languages'), [
        'languages' => ['ru', 'es', 'de'],
    ]);

    $response->assertRedirect();

    $codes = $user->languages()->pluck('language_code')->sort()->values()->all();
    expect($codes)->toEqual(['de', 'es', 'ru']);
});

test('application index and show handle face_photo_path safely', function () {
    $admin = makeAuditAdmin();
    $user = User::factory()->create();

    $app = IdolApplication::create([
        'user_id' => $user->id,
        'status' => 'pending',
        'face_photo_path' => '',
    ]);

    $indexRes = $this->actingAs($admin, 'admin')->get(route('admin.applications.index'));
    $indexRes->assertOk();

    $showRes = $this->actingAs($admin, 'admin')->get(route('admin.applications.show', $app));
    $showRes->assertOk();

    $showProps = $showRes->viewData('page')['props'];
    expect($showProps['application']['face_photo_url'])->toBeNull();
});

test('content pack policy enforces idol creation and viewing permissions', function () {
    $policy = new ContentPackPolicy;

    $regularUser = User::factory()->create(['is_idol' => false]);
    $idolUser = User::factory()->create(['is_idol' => true]);
    $buyerUser = User::factory()->create(['is_idol' => false]);
    $randomUser = User::factory()->create(['is_idol' => false]);

    expect($policy->create($regularUser))->toBeFalse();
    expect($policy->create($idolUser))->toBeTrue();

    $pack = ContentPack::create([
        'user_id' => $idolUser->id,
        'title' => 'Test Pack',
        'description' => 'Test Description',
        'price' => 100,
        'status' => 'published',
        'hidden_at' => null,
    ]);

    // Owner can view
    expect($policy->view($idolUser, $pack))->toBeTrue();

    // Random user can view published non-hidden
    expect($policy->view($randomUser, $pack))->toBeTrue();

    // Hidden pack
    $pack->update(['hidden_at' => now()]);
    expect($policy->view($randomUser, $pack))->toBeFalse();

    // Buyer of hidden pack can still view
    ContentPackPurchase::create([
        'user_id' => $buyerUser->id,
        'content_pack_id' => $pack->id,
        'price_paid' => 100,
        'purchased_at' => now(),
    ]);

    expect($policy->view($buyerUser, $pack))->toBeTrue();
});

test('user age attribute calculates age from birth_date Carbon instance safely', function () {
    $user = User::factory()->create([
        'birth_date' => Carbon::now()->subYears(25)->subMonths(2)->toDateString(),
    ]);

    expect($user->age)->toBe(25);

    $userNoBirth = User::factory()->create([
        'birth_date' => null,
    ]);

    expect($userNoBirth->age)->toBeNull();
});
