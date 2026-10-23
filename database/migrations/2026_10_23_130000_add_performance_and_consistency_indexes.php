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
        Schema::table('users', function (Blueprint $table) {
            $table->index(['is_banned', 'rating']);
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('idol_applications', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('order_status_history', function (Blueprint $table) {
            $table->index(['actor_type', 'actor_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['idol_id', 'is_hidden', 'created_at']);
        });

        Schema::table('content_pack_purchases', function (Blueprint $table) {
            $table->index(['user_id', 'viewed_at']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->index(['reference_type', 'reference_id', 'type']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->index(['is_support', 'closed_at', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_banned', 'rating']);
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('idol_applications', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('order_status_history', function (Blueprint $table) {
            $table->dropIndex(['actor_type', 'actor_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['idol_id', 'is_hidden', 'created_at']);
        });

        Schema::table('content_pack_purchases', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'viewed_at']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id', 'type']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['is_support', 'closed_at', 'updated_at']);
        });
    }
};
