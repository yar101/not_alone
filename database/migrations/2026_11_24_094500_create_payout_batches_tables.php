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
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 64)->unique();
            $table->string('status', 32)->default('pending_approval')->index(); // pending_approval, processing, completed, partially_failed, failed
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('successful_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->boolean('is_automated')->default(false);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->index()->constrained('admins')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payout_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_batch_id')->index()->constrained('payout_batches')->cascadeOnDelete();
            $table->foreignId('idol_id')->index()->constrained('users')->cascadeOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->index()->constrained('wallet_transactions')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('phone', 32);
            $table->string('sbp_bank_id', 32);
            $table->string('status', 32)->default('pending')->index(); // pending, processing, completed, failed
            $table->text('failure_reason')->nullable();
            $table->string('gateway_operation_id', 128)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['payout_batch_id', 'status']);
            $table->index(['idol_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_batch_items');
        Schema::dropIfExists('payout_batches');
    }
};
