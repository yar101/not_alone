<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewDispute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('customer can submit review for completed order and idol rating increases', function () {
    Event::fake();

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true, 'rating' => 50.00]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($customer)->postJson(route('orders.review.store', $order), [
        'rating' => 5,
        'text' => 'Отличная услуга, все очень понравилось!',
    ]);

    $response->assertOk();
    expect(Review::where('order_id', $order->id)->where('reviewer_id', $customer->id)->exists())->toBeTrue();

    $idol->refresh();
    // 5-star review has positive delta (+0.80 dampened)
    expect($idol->rating)->toBeGreaterThan(50.00);
});

test('customer cannot submit review for uncompleted order', function () {
    Event::fake();

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true, 'rating' => 50.00]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
    ]);

    $response = $this->actingAs($customer)->postJson(route('orders.review.store', $order), [
        'rating' => 5,
        'text' => 'Пока не выполнено',
    ]);

    $response->assertStatus(422);
    expect(Review::where('order_id', $order->id)->exists())->toBeFalse();
});

test('customer cannot submit duplicate reviews for the same idol', function () {
    Event::fake();

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true, 'rating' => 50.00]);

    $order1 = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
        'completed_at' => now(),
    ]);

    $order2 = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Completed,
        'completed_at' => now(),
    ]);

    // First review succeeds
    $this->actingAs($customer)->postJson(route('orders.review.store', $order1), [
        'rating' => 5,
        'text' => 'Первый отзыв',
    ])->assertOk();

    // Second review fails with already_reviewed
    $response = $this->actingAs($customer)->postJson(route('orders.review.store', $order2), [
        'rating' => 5,
        'text' => 'Второй отзыв',
    ]);

    $response->assertStatus(422);
});

test('idol can dispute a review received on their profile', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);

    $review = Review::create([
        'reviewer_id' => $customer->id,
        'idol_id' => $idol->id,
        'rating' => 1,
        'text' => 'Необоснованная критика',
    ]);

    $response = $this->actingAs($idol)->postJson(route('reviews.dispute.store', $review), [
        'reason' => 'Заказчик нарушал правила общения',
    ]);

    $response->assertOk();
    expect(ReviewDispute::where('review_id', $review->id)->where('status', 'pending')->exists())->toBeTrue();
});
