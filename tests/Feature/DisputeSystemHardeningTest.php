<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Rules\NoLinks;
use App\Rules\NoProfanity;
use App\Services\AdminLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DisputeSystemHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_links_rule_rejects_various_url_formats(): void
    {
        $invalidInputs = [
            'Посетите мой сайт https://evil.com/phishing',
            'Заходи на http://notalone.ru',
            'Мой телеграм t.me/scammer_channel',
            'Связь через vk.com/my_page',
            'Присоединяйся к discord.gg/invite_code',
            'Подключайся к 192.168.1.1 напрямую',
            'Пиши мне в инсту @cool_idol_channel',
            't.me channel_name',
            'bit.ly/123',
            'x.com/profile',
            't.co/xyz',
            'wa.me/79991234567',
            'discord.gg',
            'сайт.рф',
        ];

        foreach ($invalidInputs as $input) {
            $validator = Validator::make(
                ['text' => $input],
                ['text' => [new NoLinks]]
            );

            $this->assertTrue(
                $validator->fails(),
                "Expected NoLinks rule to fail for input: '{$input}', but it passed."
            );
        }
    }

    public function test_no_links_rule_allows_clean_text(): void
    {
        $validInputs = [
            'Отличный айдол, прекрасно пообщались и провели время!',
            'Все супер, рекомендую к заказу.',
            'Приятный голос и классная атмосфера',
        ];

        foreach ($validInputs as $input) {
            $validator = Validator::make(
                ['text' => $input],
                ['text' => [new NoLinks]]
            );

            $this->assertFalse(
                $validator->fails(),
                "Expected NoLinks rule to pass for input: '{$input}', but it failed."
            );
        }
    }

    public function test_no_profanity_rule_rejects_russian_and_english_profanity(): void
    {
        $invalidInputs = [
            'Ты полный мудак и говно',
            'Какая же х*у*й*н*я произошла',
            'What a complete bullshit and fuck you',
            'Stupid bitch and asshole',
            'Ты пиздабол и обманщик',
            'заебал этот сервис',
            'х у й',
            'п и з д а',
            'б л я т ь',
            'п и д о р',
            'охуенный вечер',
            'распиздяй',
        ];

        foreach ($invalidInputs as $input) {
            $validator = Validator::make(
                ['text' => $input],
                ['text' => [new NoProfanity]]
            );

            $this->assertTrue(
                $validator->fails(),
                "Expected NoProfanity rule to fail for input: '{$input}', but it passed."
            );
        }
    }

    public function test_no_profanity_rule_allows_normal_words(): void
    {
        $validInputs = [
            'Замечательный сервис и отзывчивый человек',
            'Спасибо большое за приятный вечер!',
            'Услуга оказана качественно и быстро',
            'Айдол показал себя настоящим профессионалом',
            'Спасибо тебе за стрим',
            'Я требую разъяснений',
            'Айдол обладает отличным голосом',
            'к успеху и результату',
        ];

        foreach ($validInputs as $input) {
            $validator = Validator::make(
                ['text' => $input],
                ['text' => [new NoProfanity]]
            );

            $this->assertFalse(
                $validator->fails(),
                "Expected NoProfanity rule to pass for input: '{$input}', but it failed."
            );
        }
    }

    public function test_order_status_disallows_transition_from_disputed_to_paid_or_cancelled(): void
    {
        $this->assertFalse(
            OrderStatus::Disputed->canTransitionTo(OrderStatus::Paid),
            'OrderStatus::Disputed should NOT transition to Paid.'
        );

        $this->assertFalse(
            OrderStatus::Disputed->canTransitionTo(OrderStatus::Cancelled),
            'OrderStatus::Disputed should NOT transition to Cancelled.'
        );

        $this->assertTrue(
            OrderStatus::Disputed->canTransitionTo(OrderStatus::Refunded),
            'OrderStatus::Disputed must transition to Refunded on dispute approval.'
        );

        $this->assertTrue(
            OrderStatus::Disputed->canTransitionTo(OrderStatus::Completed),
            'OrderStatus::Disputed must transition to Completed on dispute rejection.'
        );
    }

    public function test_order_dispute_details_max_length_validation(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        $tooLongDetails = str_repeat('a', 5001);

        $response = $this->actingAs($customer)->postJson("/orders/{$order->id}/dispute", [
            'reason' => 'service_not_provided',
            'details' => $tooLongDetails,
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_logs_record_ip_address_and_user_agent(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Tester',
            'email' => 'admin_tester_log@example.com',
            'password' => bcrypt('password'),
        ]);

        AdminLogService::log(
            adminId: $admin->id,
            action: 'test_action',
            targetType: 'order',
            targetId: 123,
            details: ['key' => 'value'],
            ipAddress: '198.51.100.42',
            userAgent: 'Mozilla/5.0 TestAgent'
        );

        $log = AdminLog::latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('198.51.100.42', $log->ip_address);
        $this->assertEquals('Mozilla/5.0 TestAgent', $log->user_agent);
    }

    public function test_review_dispute_approval_reverses_rating_symmetrically_without_dampening(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Review Tester',
            'email' => 'admin_review_test@example.com',
            'password' => bcrypt('password'),
        ]);

        $idol = User::factory()->create(['is_idol' => true, 'rating' => 80.00]);
        $reviewer = User::factory()->create();

        // 1. One-star review dispute: previously subtracted -1.2
        $review1 = Review::create([
            'idol_id' => $idol->id,
            'reviewer_id' => $reviewer->id,
            'rating' => 1,
            'text' => 'Плохо',
        ]);

        $dispute1 = \App\Models\ReviewDispute::create([
            'review_id' => $review1->id,
            'idol_id' => $idol->id,
            'reason' => 'Спам и клевета',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'admin')->patchJson("/admin/review-disputes/{$dispute1->id}/resolve", [
            'decision' => 'approved',
            'admin_note' => 'Одобрено',
        ])->assertRedirect();

        $idol->refresh();
        // Rating was 80.00, reversed +1.2 WITHOUT dampening -> 81.20
        $this->assertEquals(81.20, (float) $idol->rating);
        $this->assertTrue($review1->fresh()->is_hidden);

        // 2. Five-star review dispute: previously added +0.8 -> reversed -0.8
        $reviewer2 = User::factory()->create();
        $review5 = Review::create([
            'idol_id' => $idol->id,
            'reviewer_id' => $reviewer2->id,
            'rating' => 5,
            'text' => 'Спам реклама в 5 звезд',
        ]);

        $dispute5 = \App\Models\ReviewDispute::create([
            'review_id' => $review5->id,
            'idol_id' => $idol->id,
            'reason' => 'Реклама и спам',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'admin')->patchJson("/admin/review-disputes/{$dispute5->id}/resolve", [
            'decision' => 'approved',
            'admin_note' => 'Одобрено, спам',
        ])->assertRedirect();

        $idol->refresh();
        // Rating was 81.20, reversed -0.8 -> 80.40
        $this->assertEquals(80.40, (float) $idol->rating);
        $this->assertTrue($review5->fresh()->is_hidden);
    }

    public function test_non_idol_or_banned_user_cannot_dispute_review(): void
    {
        $nonIdol = User::factory()->create(['is_idol' => false]);
        $idol = User::factory()->create(['is_idol' => true]);
        $reviewer = User::factory()->create();

        $review = Review::create([
            'idol_id' => $idol->id,
            'reviewer_id' => $reviewer->id,
            'rating' => 2,
            'text' => 'Тест',
        ]);

        // Non-idol attempt
        $this->actingAs($nonIdol)->postJson("/reviews/{$review->id}/dispute", [
            'reason' => 'Не нравится',
        ])->assertStatus(403);

        // Active idol can_dispute flag
        $resActive = $this->actingAs($idol)->getJson("/users/{$idol->id}/reviews");
        $resActive->assertStatus(200);
        $this->assertTrue($resActive->json('reviews.0.can_dispute'));

        // Banned idol attempt
        $idol->update(['is_banned' => true, 'banned_at' => now()]);
        $this->actingAs($idol)->postJson("/reviews/{$review->id}/dispute", [
            'reason' => 'Не нравится',
        ])->assertStatus(403);

        // Banned idol sees can_dispute = false
        $resBanned = $this->actingAs($idol)->getJson("/users/{$idol->id}/reviews");
        $resBanned->assertStatus(200);
        $this->assertFalse($resBanned->json('reviews.0.can_dispute'));
    }
}
