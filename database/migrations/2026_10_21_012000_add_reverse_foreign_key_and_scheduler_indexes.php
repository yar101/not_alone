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
        Schema::table('content_pack_purchases', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('admin_broadcast_reads', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('user_traits', function (Blueprint $table) {
            $table->index('trait_id');
        });

        Schema::table('user_interests', function (Blueprint $table) {
            $table->index('interest_id');
        });

        Schema::table('user_languages', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('user_avatar_frames', function (Blueprint $table) {
            $table->index('avatar_frame_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'paid_at']);
            $table->index(['status', 'payout_released_at', 'completed_at']);
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->index(['status', 'updated_at']);
        });

        Schema::table('idol_article_versions', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('created_at');
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('trait_suggestions', function (Blueprint $table) {
            $table->index(['status', 'user_id']);
        });

        Schema::table('interest_suggestions', function (Blueprint $table) {
            $table->index(['status', 'user_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('interests', function (Blueprint $table) {
            $table->index(['category_id', 'sort_order']);
        });

        Schema::table('order_status_history', function (Blueprint $table) {
            $table->index(['order_id', 'created_at']);
        });

        Schema::table('ban_reasons', function (Blueprint $table) {
            $table->index(['type', 'sort_order']);
        });

        Schema::table('review_epithets', function (Blueprint $table) {
            $table->index('sort_order');
        });

        Schema::table('review_epithet_review', function (Blueprint $table) {
            $table->index('review_epithet_id');
        });

        Schema::table('service_price_limits', function (Blueprint $table) {
            $table->index('time_unit_id');
        });

        Schema::table('idol_quiz_session_questions', function (Blueprint $table) {
            $table->index('question_id');
        });

        Schema::table('post_comments', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('user_strikes', function (Blueprint $table) {
            $table->index('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_pack_purchases', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('admin_broadcast_reads', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('user_traits', function (Blueprint $table) {
            $table->dropIndex(['trait_id']);
        });

        Schema::table('user_interests', function (Blueprint $table) {
            $table->dropIndex(['interest_id']);
        });

        Schema::table('user_languages', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('user_avatar_frames', function (Blueprint $table) {
            $table->dropIndex(['avatar_frame_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'paid_at']);
            $table->dropIndex(['status', 'payout_released_at', 'completed_at']);
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->dropIndex(['status', 'updated_at']);
        });

        Schema::table('idol_article_versions', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('trait_suggestions', function (Blueprint $table) {
            $table->dropIndex(['status', 'user_id']);
        });

        Schema::table('interest_suggestions', function (Blueprint $table) {
            $table->dropIndex(['status', 'user_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('interests', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'sort_order']);
        });

        Schema::table('order_status_history', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'created_at']);
        });

        Schema::table('ban_reasons', function (Blueprint $table) {
            $table->dropIndex(['type', 'sort_order']);
        });

        Schema::table('review_epithets', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
        });

        Schema::table('review_epithet_review', function (Blueprint $table) {
            $table->dropIndex(['review_epithet_id']);
        });

        Schema::table('service_price_limits', function (Blueprint $table) {
            $table->dropIndex(['time_unit_id']);
        });

        Schema::table('idol_quiz_session_questions', function (Blueprint $table) {
            $table->dropIndex(['question_id']);
        });

        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('user_strikes', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
        });
    }
};
