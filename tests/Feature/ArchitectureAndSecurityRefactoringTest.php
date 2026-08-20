<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('banned user is intercepted across orders and conversations routes', function () {
    $bannedUser = User::factory()->create([
        'is_banned' => true,
        'banned_at' => now(),
        'ban_reason' => 'Тестовая блокировка',
    ]);

    // Attempt to access orders
    $resOrders = $this->actingAs($bannedUser)->getJson(route('orders.index'));
    $resOrders->assertStatus(403);

    // Attempt to access conversations
    $resConversations = $this->actingAs($bannedUser)->getJson(route('conversations.index'));
    $resConversations->assertStatus(403);
});

test('order policy restricts unauthorized users from viewing or mutating orders', function () {
    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true]);
    $stranger = User::factory()->create();

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Pending,
    ]);

    // Stranger cannot accept order
    $this->actingAs($stranger)->patchJson(route('orders.accept', $order))
        ->assertForbidden();

    // Customer cannot accept order (only idol can)
    $this->actingAs($customer)->patchJson(route('orders.accept', $order))
        ->assertForbidden();

    // Idol CAN accept order
    $this->actingAs($idol)->patchJson(route('orders.accept', $order))
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Accepted);

    // Idol cannot pay (only customer can)
    $this->actingAs($idol)->patchJson(route('orders.pay', $order))
        ->assertForbidden();

    // Stranger cannot pay
    $this->actingAs($stranger)->patchJson(route('orders.pay', $order))
        ->assertForbidden();

    // Customer CAN pay
    $this->actingAs($customer)->patchJson(route('orders.pay', $order))
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('confirmCompletion handles both idol and customer confirmations safely and atomically', function () {
    Event::fake();

    $customer = User::factory()->create();
    $idol = User::factory()->create(['is_idol' => true, 'rating' => 50]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
    ]);

    $service = app(OrderService::class);

    // Idol confirms completion (waiting on customer)
    $res1 = $service->confirmCompletion($order, $idol);
    expect($res1['confirmed'])->toBeTrue();
    expect($order->fresh()->completion_confirmed_by_idol)->toBeTrue();
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    // Customer confirms completion -> order becomes Completed
    $res2 = $service->confirmCompletion($order, $customer);
    expect($res2['confirmed'])->toBeTrue();
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    expect($order->fresh()->completed_at)->not->toBeNull();

    // Calling confirmCompletion again on already completed order is idempotent
    $res3 = $service->confirmCompletion($order, $customer);
    expect($res3['confirmed'])->toBeTrue();
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('post comments accurately count all comments while rootComments only fetches top-level', function () {
    $author = User::factory()->create();
    $post = Post::create([
        'user_id' => $author->id,
        'body' => 'Post with nested comments',
    ]);

    // Create 2 root comments
    $c1 = PostComment::create(['post_id' => $post->id, 'user_id' => $author->id, 'body' => 'Root 1']);
    $c2 = PostComment::create(['post_id' => $post->id, 'user_id' => $author->id, 'body' => 'Root 2']);

    // Create 3 replies to Root 1
    PostComment::create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $c1->id, 'body' => 'Reply 1']);
    PostComment::create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $c1->id, 'body' => 'Reply 2']);
    PostComment::create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $c1->id, 'body' => 'Reply 3']);

    // Total comments must be 5
    expect($post->comments()->count())->toBe(5);

    // Root comments must be 2
    expect($post->rootComments()->count())->toBe(2);
});
