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
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->index(['user_id', 'has_unread']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'status']);
            $table->index(['idol_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('chat_blocks', function (Blueprint $table) {
            $table->index(['blocker_id', 'blocked_id', 'blocked_until']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['idol_id', 'is_hidden', 'rating']);
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'has_unread']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'status']);
            $table->dropIndex(['idol_id', 'status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('chat_blocks', function (Blueprint $table) {
            $table->dropIndex(['blocker_id', 'blocked_id', 'blocked_until']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['idol_id', 'is_hidden', 'rating']);
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'published_at']);
        });
    }
};
