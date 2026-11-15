<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('customer_service_fee', 12, 2)->default(0.00)->after('status');
            $table->decimal('customer_service_fee_percent', 5, 2)->default(0.00)->after('customer_service_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_service_fee', 'customer_service_fee_percent']);
        });
    }
};
