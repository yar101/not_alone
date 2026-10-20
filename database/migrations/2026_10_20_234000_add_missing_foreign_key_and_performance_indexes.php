<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('service_id');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('content_pack_photos', function (Blueprint $table) {
            $table->index(['content_pack_id', 'sort_order']);
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->index('idol_id');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index('time_unit_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->index('order_id');
            $table->index(['is_support', 'updated_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('conversation_id');
            $table->index(['customer_id', 'idol_id']);
        });

        Schema::table('order_disputes', function (Blueprint $table) {
            $table->index('order_id');
            $table->index(['customer_id', 'status']);
        });

        Schema::table('review_disputes', function (Blueprint $table) {
            $table->index('review_id');
            $table->index(['idol_id', 'status']);
        });

        Schema::table('user_strikes', function (Blueprint $table) {
            $table->index(['user_id', 'expires_at']);
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->index(['reported_id', 'status']);
            $table->index('reporter_id');
        });

        Schema::table('idol_rating_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('admin_logs', function (Blueprint $table) {
            $table->index(['admin_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index(['action', 'created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notifications_notifiable_created_idx');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('idol_quiz_cooldown_until');
        });

        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->index(['target', 'created_at']);
            $table->index('target_user_id');
        });

        Schema::table('help_articles', function (Blueprint $table) {
            $table->index(['category_id', 'sort_order']);
        });

        Schema::table('content_pack_change_requests', function (Blueprint $table) {
            $table->index(['content_pack_id', 'status']);
        });

        Schema::table('service_change_requests', function (Blueprint $table) {
            $table->index(['service_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['service_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('content_pack_photos', function (Blueprint $table) {
            $table->dropIndex(['content_pack_id', 'sort_order']);
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->dropIndex(['idol_id']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['time_unit_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['is_support', 'updated_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['conversation_id']);
            $table->dropIndex(['customer_id', 'idol_id']);
        });

        Schema::table('order_disputes', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['customer_id', 'status']);
        });

        Schema::table('review_disputes', function (Blueprint $table) {
            $table->dropIndex(['review_id']);
            $table->dropIndex(['idol_id', 'status']);
        });

        Schema::table('user_strikes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'expires_at']);
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropIndex(['reported_id', 'status']);
            $table->dropIndex(['reporter_id']);
        });

        Schema::table('idol_rating_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('admin_logs', function (Blueprint $table) {
            $table->dropIndex(['admin_id', 'created_at']);
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropIndex(['action', 'created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_created_idx');
            $table->dropIndex('notifications_notifiable_read_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['idol_quiz_cooldown_until']);
        });

        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->dropIndex(['target', 'created_at']);
            $table->dropIndex(['target_user_id']);
        });

        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'sort_order']);
        });

        Schema::table('content_pack_change_requests', function (Blueprint $table) {
            $table->dropIndex(['content_pack_id', 'status']);
        });

        Schema::table('service_change_requests', function (Blueprint $table) {
            $table->dropIndex(['service_id', 'status']);
        });
    }
};
