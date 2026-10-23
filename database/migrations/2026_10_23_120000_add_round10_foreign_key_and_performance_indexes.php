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
        Schema::table('messages', function (Blueprint $table) {
            $table->index('sender_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('active_frame_id');
            $table->index('banned_by');
            $table->index('deleted_at');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index('moderated_by');
            $table->index('deleted_at');
            $table->index(['status', 'created_at']);
        });

        Schema::table('idol_applications', function (Blueprint $table) {
            $table->index('reviewed_by');
        });

        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->index('admin_id');
        });

        Schema::table('idol_category_descriptions', function (Blueprint $table) {
            $table->index('category_id');
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->index('reviewed_by');
            $table->index(['status', 'created_at']);
        });

        Schema::table('post_likes', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('cancelled_by');
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->index('moderated_by');
            $table->index('deleted_at');
        });

        Schema::table('content_pack_reviews', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('content_pack_id');
        });

        Schema::table('content_pack_change_requests', function (Blueprint $table) {
            $table->index('reviewed_by');
            $table->index(['status', 'created_at']);
        });

        Schema::table('service_change_requests', function (Blueprint $table) {
            $table->index('pending_category_id');
            $table->index('pending_time_unit_id');
            $table->index('reviewed_by');
            $table->index(['status', 'created_at']);
        });

        Schema::table('service_reviews', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('service_id');
        });

        Schema::table('user_idol_trials', function (Blueprint $table) {
            $table->index('idol_id');
            $table->index('order_id');
        });

        Schema::table('trait_suggestions', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('interest_suggestions', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('order_disputes', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('review_disputes', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('admin_logs', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('idol_quiz_sessions', function (Blueprint $table) {
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sender_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['active_frame_id']);
            $table->dropIndex(['banned_by']);
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['moderated_by']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('idol_applications', function (Blueprint $table) {
            $table->dropIndex(['reviewed_by']);
        });

        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
        });

        Schema::table('idol_category_descriptions', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropIndex(['reviewed_by']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['cancelled_by']);
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->dropIndex(['moderated_by']);
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('content_pack_reviews', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['content_pack_id']);
        });

        Schema::table('content_pack_change_requests', function (Blueprint $table) {
            $table->dropIndex(['reviewed_by']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('service_change_requests', function (Blueprint $table) {
            $table->dropIndex(['pending_category_id']);
            $table->dropIndex(['pending_time_unit_id']);
            $table->dropIndex(['reviewed_by']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('service_reviews', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['service_id']);
        });

        Schema::table('user_idol_trials', function (Blueprint $table) {
            $table->dropIndex(['idol_id']);
            $table->dropIndex(['order_id']);
        });

        Schema::table('trait_suggestions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('interest_suggestions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('order_disputes', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('review_disputes', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('admin_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('idol_quiz_sessions', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
