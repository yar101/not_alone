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
        Schema::create('coin_pack_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coin_pack_id')->nullable()->constrained('coin_packs')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->decimal('coins', 12, 2);
            $table->decimal('fee_amount', 12, 2)->default(0.00);
            $table->decimal('net_amount', 12, 2);
            $table->boolean('is_starter')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_starter']);
            $table->index('coin_pack_id');
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_pack_purchases');
    }
};
