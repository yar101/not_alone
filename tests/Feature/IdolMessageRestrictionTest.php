<?php

use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('non-idol user can toggle disallow_idol_messages', function () {
    $user = User::factory()->create([
        'is_idol' => false,
        'disallow_idol_messages' => false,
    ]);

    $this->actingAs($user)
        ->patchJson(route('profile.settings.disallow-idol-messages'), ['disallow' => true])
        ->assertOk()
        ->assertJson(['disallow_idol_messages' => true]);

    expect($user->fresh()->disallow_idol_messages)->toBeTrue();

    $this->actingAs($user)
        ->patchJson(route('profile.settings.disallow-idol-messages'))
        ->assertOk()
        ->assertJson(['disallow_idol_messages' => false]);

    expect($user->fresh()->disallow_idol_messages)->toBeFalse();
});

test('idol cannot toggle disallow_idol_messages', function () {
    $idol = User::factory()->create(['is_idol' => true]);

    $this->actingAs($idol)
        ->patchJson(route('profile.settings.disallow-idol-messages'), ['disallow' => true])
        ->assertForbidden();
});

test('conversations.check returns disallow_idol_messages status for idol', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $user = User::factory()->create([
        'is_idol' => false,
        'disallow_idol_messages' => true,
    ]);

    $this->actingAs($idol)
        ->getJson(route('conversations.check', $user))
        ->assertOk()
        ->assertJson([
            'disallow_idol_messages' => true,
        ]);
});

test('idol cannot start direct conversation if target user disallowed idol messages', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $user = User::factory()->create([
        'is_idol' => false,
        'disallow_idol_messages' => true,
    ]);

    $this->actingAs($idol)
        ->postJson(route('conversations.store'), [
            'target_user_id' => $user->id,
        ])
        ->assertForbidden();
});

test('idol cannot send message in direct conversation if target user disallowed idol messages', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $user = User::factory()->create([
        'is_idol' => false,
        'disallow_idol_messages' => true,
    ]);

    $conversation = Conversation::create();
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $idol->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $this->actingAs($idol)
        ->postJson(route('conversations.message', $conversation), [
            'body' => 'Hello!',
        ])
        ->assertForbidden();
});

test('non-idol user can send message to idol even when disallow_idol_messages is true', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $user = User::factory()->create([
        'is_idol' => false,
        'disallow_idol_messages' => true,
    ]);

    $conversation = Conversation::create();
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $idol->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson(route('conversations.message', $conversation), [
            'body' => 'Hi idol, I am writing to you!',
        ])
        ->assertOk()
        ->assertJson([
            'body' => 'Hi idol, I am writing to you!',
            'sender_id' => $user->id,
        ]);
});

test('messages in order conversation are allowed even when user disallowed idol messages', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $customer = User::factory()->create([
        'is_idol' => false,
        'disallow_idol_messages' => true,
    ]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'idol_id' => $idol->id,
        'status' => OrderStatus::Accepted,
    ]);

    $conversation = Conversation::create(['order_id' => $order->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $idol->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $customer->id]);

    $this->actingAs($idol)
        ->postJson(route('conversations.message', $conversation), [
            'body' => 'Order discussion message',
        ])
        ->assertOk()
        ->assertJson([
            'body' => 'Order discussion message',
            'sender_id' => $idol->id,
        ]);
});

test('conversations.show indicates other_disallows_idol_messages correctly', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $user = User::factory()->create([
        'is_idol' => false,
        'disallow_idol_messages' => true,
    ]);

    $conversation = Conversation::create();
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $idol->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    // Idol viewing direct chat: other_disallows_idol_messages should be true
    $this->actingAs($idol)
        ->getJson(route('conversations.show', $conversation))
        ->assertOk()
        ->assertJson([
            'other_disallows_idol_messages' => true,
        ]);

    // Non-idol viewing direct chat: other_disallows_idol_messages should be false
    $this->actingAs($user)
        ->getJson(route('conversations.show', $conversation))
        ->assertOk()
        ->assertJson([
            'other_disallows_idol_messages' => false,
        ]);
});
