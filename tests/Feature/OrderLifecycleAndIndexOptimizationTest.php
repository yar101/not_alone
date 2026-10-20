<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\IdolPayoutReleasedNotification;
use App\Notifications\OrderAcceptedNotification;
use App\Notifications\OrderPaidNotification;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderLifecycleAndIndexOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);
        $this->orderService = app(OrderService::class);
    }

    public function test_new_database_indexes_exist_in_postgresql(): void
    {
        $expectedIndexes = [
            'content_pack_purchases_user_id_index',
            'admin_broadcast_reads_user_id_index',
            'user_traits_trait_id_index',
            'user_interests_interest_id_index',
            'user_languages_user_id_index',
            'user_avatar_frames_avatar_frame_id_index',
            'orders_status_paid_at_index',
            'orders_status_payout_released_at_completed_at_index',
            'content_packs_status_updated_at_index',
            'idol_article_versions_is_active_index',
            'idol_article_versions_created_at_index',
            'user_reports_status_index',
            'trait_suggestions_status_user_id_index',
            'interest_suggestions_status_user_id_index',
            'reviews_order_id_index',
            'interests_category_id_sort_order_index',
            'order_status_history_order_id_created_at_index',
            'ban_reasons_type_sort_order_index',
            'review_epithets_sort_order_index',
            'review_epithet_review_review_epithet_id_index',
            'service_price_limits_time_unit_id_index',
            'idol_quiz_session_questions_question_id_index',
            'post_comments_user_id_index',
            'user_strikes_admin_id_index',
        ];

        $existingIndexes = DB::table('pg_indexes')
            ->whereIn('indexname', $expectedIndexes)
            ->pluck('indexname')
            ->toArray();

        foreach ($expectedIndexes as $indexName) {
            $this->assertContains($indexName, $existingIndexes, "Index {$indexName} is missing in PostgreSQL.");
        }
    }

    public function test_orders_auto_complete_command_processes_overdue_paid_orders(): void
    {
        PlatformSetting::set('order_auto_complete_delay', 24);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);
        $this->walletService->deposit($customer, 1000, 'Init balance', null, [], false);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now()->subHours(25),
        ]);
        $this->walletService->hold($customer, 500, $order, 'Order hold');

        $this->artisan('orders:auto-complete')
            ->expectsOutputToContain('Auto-completed 1 overdue orders.')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_orders_release_idol_holds_command_processes_completed_orders(): void
    {
        PlatformSetting::set('order_dispute_window_minutes', 60);

        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($customer, 1000, 'Init balance', null, [], false);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Completed,
            'paid_at' => now()->subHours(5),
            'completed_at' => now(),
            'payout_released_at' => null,
        ]);
        $this->walletService->hold($customer, 500, $order, 'Order hold');
        $this->walletService->releaseHold($order);

        // Advance completed_at past dispute window
        $order->update(['completed_at' => now()->subHours(2)]);

        $this->artisan('orders:release-idol-holds')
            ->expectsOutputToContain('Released 1 idol hold payouts.')
            ->assertExitCode(0);

        $order->refresh();
        $this->assertNotNull($order->payout_released_at);
    }

    public function test_order_service_transitions_survive_notification_exceptions(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($customer, 5000, 'Init balance', null, [], false);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Pending,
        ]);

        // Mock customer throwing on OrderAcceptedNotification
        $customerMock = \Mockery::mock($customer)->makePartial();
        $customerMock->shouldReceive('notify')
            ->with(\Mockery::type(OrderAcceptedNotification::class))
            ->andThrow(new \RuntimeException('Mail server connection failed'));

        $order->setRelation('customer', $customerMock);

        // accept() should not throw despite notification failure
        $this->orderService->accept($order, $idol);
        $order->refresh();
        $this->assertEquals(OrderStatus::Accepted, $order->status);

        // pay() should not throw despite notification failure
        $idolMock = \Mockery::mock($idol)->makePartial();
        $idolMock->shouldReceive('notify')
            ->with(\Mockery::type(OrderPaidNotification::class))
            ->andThrow(new \RuntimeException('Push server down'));

        $order->setRelation('idol', $idolMock);
        $this->orderService->pay($order, $customer);
        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
    }

    public function test_order_service_cancel_survives_notification_exception(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Pending,
        ]);

        // User::find inside cancel() returns counterparty
        $idolMock = \Mockery::mock($idol)->makePartial();
        $idolMock->shouldReceive('notify')
            ->andThrow(new \RuntimeException('Notification driver crashed'));

        $order->setRelation('idol', $idolMock);

        $this->orderService->cancel($order, $customer, 'Changed mind');

        $order->refresh();
        $this->assertEquals(OrderStatus::Cancelled, $order->status);
    }

    public function test_wallet_service_release_idol_payout_survives_notification_exception(): void
    {
        $customer = User::factory()->create();
        $idol = User::factory()->create(['is_idol' => true]);

        $this->walletService->deposit($customer, 1000, 'Init balance', null, [], false);

        $order = Order::create([
            'customer_id' => $customer->id,
            'idol_id' => $idol->id,
            'status' => OrderStatus::Completed,
            'paid_at' => now()->subHours(5),
            'completed_at' => now(),
            'payout_released_at' => null,
        ]);
        $this->walletService->hold($customer, 500, $order, 'Order hold');
        $this->walletService->releaseHold($order);

        // Advance completed_at past dispute window
        $order->update(['completed_at' => now()->subHours(2)]);

        $idolMock = \Mockery::mock($idol)->makePartial();
        $idolMock->shouldReceive('notify')
            ->with(\Mockery::type(IdolPayoutReleasedNotification::class))
            ->andThrow(new \RuntimeException('Notification delivery failed'));

        $order->setRelation('idol', $idolMock);

        $tx = $this->walletService->releaseIdolPayout($order);

        $this->assertNotNull($tx);
        $order->refresh();
        $this->assertNotNull($order->payout_released_at);
    }

    public function test_export_controller_streams_users_and_orders_cleanly(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Test',
            'email' => 'adm_export_test@example.com',
            'password' => bcrypt('password'),
        ]);
        $users = User::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.export.users'));
        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $order = Order::create([
            'customer_id' => $users[0]->id,
            'idol_id' => $users[1]->id,
            'status' => OrderStatus::Completed,
        ]);

        $orderResponse = $this->actingAs($admin, 'admin')->get(route('admin.export.orders'));
        $orderResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $orderResponse->headers->get('content-type'));
    }
}
