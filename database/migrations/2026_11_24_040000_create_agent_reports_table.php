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
        Schema::create('agent_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idol_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_number', 50)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('gross_sales', 12, 2)->default(0.00);
            $table->decimal('platform_fee_amount', 12, 2)->default(0.00);
            $table->decimal('customer_service_fee_amount', 12, 2)->default(0.00);
            $table->decimal('net_payout_amount', 12, 2)->default(0.00);
            $table->string('status', 30)->default('pending_acceptance');
            $table->timestamp('acceptance_deadline')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->jsonb('items_breakdown')->nullable();
            $table->timestamps();

            $table->index(['idol_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_reports');
    }
};
