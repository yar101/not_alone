<?php

use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('idol can start a direct conversation with a user and exchange messages', function () {
    Event::fake();

    $userA = User::factory()->create(['is_idol' => true]);
    $userB = User::factory()->create(['is_idol' => false]);

    // Idol user A initiates conversation
    $response = $this->actingAs($userA)->postJson(route('conversations.store'), [
        'target_user_id' => $userB->id,
    ]);

    $response->assertOk();
    $conversationId = $response->json('conversation_id');
    expect($conversationId)->not->toBeNull();

    $conversation = Conversation::find($conversationId);
    expect($conversation->participants)->toHaveCount(2);

    // User A sends a message
    $msgResponse = $this->actingAs($userA)->postJson(route('conversations.message', $conversation), [
        'body' => 'Привет, как дела?',
    ]);

    $msgResponse->assertOk();
    expect($msgResponse->json('body'))->toBe('Привет, как дела?');

    // Verify recipient participant has_unread = true
    $participantB = ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $userB->id)
        ->first();
    expect($participantB->has_unread)->toBeTrue();

    // User B opens the conversation -> marked as read
    $showResponse = $this->actingAs($userB)->getJson(route('conversations.show', $conversation));
    $showResponse->assertOk();

    expect($participantB->fresh()->has_unread)->toBeFalse();
});

test('blocked user cannot send messages and receives 403', function () {
    Event::fake();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conversation = Conversation::create();
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $userA->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $userB->id]);

    // User A blocks User B
    $blockResponse = $this->actingAs($userA)->postJson(route('conversations.block', $conversation), [
        'reason' => 'Спам',
    ]);
    $blockResponse->assertOk();
    expect(ChatBlock::where('blocker_id', $userA->id)->where('blocked_id', $userB->id)->exists())->toBeTrue();

    // User B tries to send message -> 403 Forbidden
    $msgResponse = $this->actingAs($userB)->postJson(route('conversations.message', $conversation), [
        'body' => 'Ты меня слышишь?',
    ]);
    $msgResponse->assertStatus(403);

    // User A unblocks User B
    $unblockResponse = $this->actingAs($userA)->deleteJson(route('conversations.unblock', $conversation));
    $unblockResponse->assertOk();
    expect(ChatBlock::where('blocker_id', $userA->id)->where('blocked_id', $userB->id)->exists())->toBeFalse();

    // User B can send message now
    $msgResponse2 = $this->actingAs($userB)->postJson(route('conversations.message', $conversation), [
        'body' => 'Снова на связи!',
    ]);
    $msgResponse2->assertOk();
});

test('non-participant user cannot access conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $stranger = User::factory()->create();

    $conversation = Conversation::create();
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $userA->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $userB->id]);

    $response = $this->actingAs($stranger)->getJson(route('conversations.show', $conversation));
    $response->assertStatus(403);
});
