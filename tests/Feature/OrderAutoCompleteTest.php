<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\CompleteOrderJob;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches CompleteOrderJob when an order is paid', function () {
    Queue::fake();
    Event::fake();

    $customer = User::factory()->create();
    $idol     = User::factory()->create(['is_idol' => true]);
    
    // Ensure we have a conversation for the order
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id'     => $idol->id,
        'status'      => OrderStatus::Accepted,
    ]);

    $service = app(OrderService::class);
    $service->pay($order, $customer);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    Queue::assertPushed(CompleteOrderJob::class, function ($job) use ($order) {
        return $job->order->id === $order->id && $job->delay !== null;
    });
});

it('completes the order when the job is executed', function () {
    $customer = User::factory()->create();
    $idol     = User::factory()->create(['is_idol' => true]);
    $order    = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id'     => $idol->id,
        'status'      => OrderStatus::Paid,
        'paid_at'     => now()->subHours(72),
    ]);

    $job = new CompleteOrderJob($order);
    $job->handle(app(OrderService::class));

    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

it('does not complete the order if status is not paid', function () {
    $customer = User::factory()->create();
    $idol     = User::factory()->create(['is_idol' => true]);
    $order    = Order::factory()->create([
        'customer_id' => $customer->id,
        'idol_id'     => $idol->id,
        'status'      => OrderStatus::Cancelled, // Order was cancelled manually
    ]);

    $job = new CompleteOrderJob($order);
    $job->handle(app(OrderService::class));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});
