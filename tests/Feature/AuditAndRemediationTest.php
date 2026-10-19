<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Events\MessageSent;
use App\Models\AvatarFrame;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuditAndRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_show_does_not_mutate_paid_at_instance(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $conversation = Conversation::create();
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $customer->id]);
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $idol->id]);

        $originalPaidAt = Carbon::parse('2026-05-01 10:00:00');
        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Paid,
            'paid_at' => $originalPaidAt,
        ]);
        $conversation->update(['order_id' => $order->id]);

        $response = $this->actingAs($customer)->get(route('conversations.show', $conversation));
        $response->assertStatus(200);

        // Verify order in database and reloaded model has original paid_at unchanged
        $order->refresh();
        $this->assertEquals($originalPaidAt->timestamp, $order->paid_at->timestamp);
    }

    public function test_message_response_and_event_include_sender_frame(): void
    {
        Event::fake([MessageSent::class]);

        $frame = AvatarFrame::create([
            'name' => 'Golden Ring',
            'image_path' => 'frames/gold.png',
            'type' => 'free',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'active_frame_id' => $frame->id,
        ]);
        $target = User::factory()->create();

        $conversation = Conversation::create();
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $target->id]);

        $response = $this->actingAs($user)->postJson(route('conversations.message', $conversation), [
            'body' => 'Hello from framed user',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('sender_frame.id', $frame->id);
        $response->assertJsonPath('sender_frame.name', 'Golden Ring');

        Event::assertDispatched(MessageSent::class, function ($event) use ($frame) {
            $data = $event->broadcastWith();

            return isset($data['sender_frame']) && $data['sender_frame']['id'] === $frame->id;
        });
    }

    public function test_order_completion_invalidates_newbie_cache(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        $cacheKey = "user_is_newbie_{$idol->id}";
        Cache::put($cacheKey, true, 3600);
        $this->assertTrue(Cache::has($cacheKey));

        $orderService = app(OrderService::class);
        $orderService->autoCompleteOrder($order);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_auto_complete_and_release_idol_holds_commands_execute_without_error(): void
    {
        PlatformSetting::set('order_auto_complete_delay', 72);
        PlatformSetting::set('order_dispute_window_minutes', 60);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now()->subHours(80),
        ]);

        $this->artisan('orders:auto-complete')->assertExitCode(0);
        $this->artisan('orders:release-idol-holds')->assertExitCode(0);
    }
}
