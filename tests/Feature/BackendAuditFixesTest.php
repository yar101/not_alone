<?php

use App\Enums\OrderStatus;
use App\Models\IdolQuizQuestion;
use App\Models\IdolQuizSession;
use App\Models\IdolQuizSessionQuestion;
use App\Models\Order;
use App\Models\Post;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePriceLimit;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use App\Services\IdolRatingService;
use App\Services\UserProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user rating is cast as float and supports decimal increments', function () {
    $user = User::factory()->create([
        'is_idol' => true,
        'rating' => 50.50,
    ]);

    expect($user->rating)->toBeFloat();
    expect($user->rating)->toEqual(50.50);

    IdolRatingService::adjust($user, 'order_completed');
    $user->refresh();

    // order_completed delta is +0.20, dampened asymptotically
    expect($user->rating)->toBeGreaterThan(50.50);
    expect($user->rating)->toBeFloat();
});

test('user profile service creates comments with body and nested parent_id', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    $service = app(UserProfileService::class);

    $parentComment = $service->createComment($user, $post, [
        'body' => 'Main comment text',
        'parent_id' => null,
    ]);

    expect($parentComment->body)->toBe('Main comment text');
    expect($parentComment->parent_id)->toBeNull();
    expect($parentComment->user_id)->toBe($user->id);

    $childComment = $service->createComment($user, $post, [
        'body' => 'Reply comment text',
        'parent_id' => $parentComment->id,
    ]);

    expect($childComment->body)->toBe('Reply comment text');
    expect($childComment->parent_id)->toBe($parentComment->id);
});

test('order transition validation allows valid transitions and blocks invalid ones', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Pending,
    ]);

    $orderService = app(\App\Services\OrderService::class);

    // Pending -> Accepted is valid
    $orderService->accept($order, $idol);
    expect($order->fresh()->status)->toBe(OrderStatus::Accepted);

    // Accepted -> Paid is valid
    $orderService->pay($order, $customer);
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    // Paid -> Cannot accept again (invalid transition)
    expect(fn () => $orderService->accept($order->fresh(), $idol))
        ->toThrow(\DomainException::class);
});

test('quiz stage progression cannot skip stages to stage 10', function () {
    $user = User::factory()->create();

    $session = IdolQuizSession::create([
        'user_id' => $user->id,
        'status' => 'active',
        'attempt_number' => 1,
        'errors_count' => 0,
    ]);

    for ($i = 1; $i <= 10; $i++) {
        $q = IdolQuizQuestion::create([
            'stage' => $i,
            'question' => "Question {$i}",
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option_index' => 0,
            'sort_order' => 1,
        ]);

        IdolQuizSessionQuestion::create([
            'session_id' => $session->id,
            'question_id' => $q->id,
            'stage' => $i,
        ]);
    }

    // Try answering stage 10 directly without answering stages 1-9
    $response = $this->actingAs($user)->postJson(route('idol.quiz.answer'), [
        'session_id' => $session->id,
        'stage' => 10,
        'answer_index' => 0,
    ]);

    $response->assertStatus(422);
    expect($user->fresh()->idol_quiz_passed_at)->toBeNull();
});

test('admin strike search with text string does not crash postgres integer syntax', function () {
    $user = User::factory()->create(['name' => 'Александр']);

    $admin = \App\Models\Admin::create([
        'name' => 'Admin',
        'email' => 'admin@test.local',
        'password' => bcrypt('password'),
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.strikes.index', ['q' => 'Александр']));
    $response->assertOk();
});

test('service controller validates price limit based on user rating below threshold', function () {
    $category = ServiceCategory::create(['name' => ['ru' => 'Тест', 'en' => 'Test'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour'], 'sort_order' => 1]);

    ServicePriceLimit::create([
        'time_unit_id' => $timeUnit->id,
        'max_price' => 500,
    ]);

    // Idol with low rating (25.00 < 30)
    $idol = User::factory()->create([
        'is_idol' => true,
        'rating' => 25.00,
    ]);

    $response = $this->actingAs($idol)->post(route('profile.services.store'), [
        'name_ru' => 'Консультация',
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'price' => 1000, // exceeds max_price of 500
    ]);

    $response->assertSessionHasErrors('price');
});

test('order service blocks second trial order for same idol', function () {
    $category = ServiceCategory::create(['name' => ['ru' => 'Тест', 'en' => 'Test'], 'sort_order' => 1]);
    $timeUnit = ServiceTimeUnit::create(['name' => ['ru' => 'час', 'en' => 'hour'], 'sort_order' => 1]);

    $idol = User::factory()->create(['is_idol' => true, 'rating' => 60]);
    $service = Service::create([
        'user_id' => $idol->id,
        'category_id' => $category->id,
        'time_unit_id' => $timeUnit->id,
        'name' => ['ru' => 'Пробная услуга'],
        'price' => 500,
        'is_trial' => true,
        'is_active' => true,
        'status' => 'approved',
    ]);

    $customer = User::factory()->create();
    $orderService = app(\App\Services\OrderService::class);

    // First trial order succeeds
    [$order1, $conv1] = $orderService->createOrder($customer, $idol, [
        ['id' => $service->id, 'quantity' => 1],
    ]);
    expect($order1->id)->not->toBeNull();

    // Second trial order must throw exception
    expect(fn () => $orderService->createOrder($customer, $idol, [
        ['id' => $service->id, 'quantity' => 1],
    ]))->toThrow(\Exception::class, 'Вы уже использовали бесплатный первый заказ у этого пользователя');
});
