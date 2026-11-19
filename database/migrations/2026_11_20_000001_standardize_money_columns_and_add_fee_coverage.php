<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. content_pack_purchases: decimal price, amount_charged and fee coverage flags
        Schema::table('content_pack_purchases', function (Blueprint $table) {
            $table->decimal('price_paid', 12, 2)->change();
            $table->decimal('amount_charged', 12, 2)->nullable()->after('price_paid');
            $table->boolean('fee_covered_platform')->default(false)->after('amount_charged');
            $table->boolean('fee_covered_withdrawal')->default(false)->after('fee_covered_platform');
        });

        // 2. order_items: standardize price to decimal(12, 2)
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->change();
        });

        // 3. content_packs & content_pack_change_requests
        Schema::table('content_packs', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->change();
        });

        Schema::table('content_pack_change_requests', function (Blueprint $table) {
            $table->decimal('pending_price', 12, 2)->nullable()->change();
        });

        // 4. services, change requests & limits
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->change();
        });

        Schema::table('service_change_requests', function (Blueprint $table) {
            $table->decimal('pending_price', 12, 2)->nullable()->change();
        });

        Schema::table('service_price_limits', function (Blueprint $table) {
            $table->decimal('max_price', 12, 2)->change();
            $table->decimal('low_rating_max_price', 12, 2)->change();
        });

        // 5. avatar_frames: standardize price precision
        Schema::table('avatar_frames', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0.00)->change();
        });

        // 6. orders: fee coverage flags and covered amount
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('fee_covered_platform')->default(false)->after('customer_service_fee_percent');
            $table->boolean('fee_covered_withdrawal')->default(false)->after('fee_covered_platform');
            $table->decimal('idol_fee_covered', 12, 2)->default(0.00)->after('fee_covered_withdrawal');
        });

        // 7. donations: withdrawal fee coverage
        Schema::table('donations', function (Blueprint $table) {
            $table->boolean('fee_covered_withdrawal')->default(false)->after('fee_covered_by_donor');
            $table->decimal('withdrawal_fee', 12, 2)->default(0.00)->after('platform_fee');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn(['fee_covered_withdrawal', 'withdrawal_fee']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fee_covered_platform', 'fee_covered_withdrawal', 'idol_fee_covered']);
        });

        Schema::table('avatar_frames', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0.00)->change();
        });

        Schema::table('service_price_limits', function (Blueprint $table) {
            $table->integer('max_price')->change();
            $table->integer('low_rating_max_price')->change();
        });

        Schema::table('service_change_requests', function (Blueprint $table) {
            $table->integer('pending_price')->nullable()->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('price')->change();
        });

        Schema::table('content_pack_change_requests', function (Blueprint $table) {
            $table->integer('pending_price')->nullable()->change();
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->unsignedInteger('price')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('price')->nullable()->change();
        });

        Schema::table('content_pack_purchases', function (Blueprint $table) {
            $table->dropColumn(['amount_charged', 'fee_covered_platform', 'fee_covered_withdrawal']);
            $table->unsignedInteger('price_paid')->change();
        });
    }
};
