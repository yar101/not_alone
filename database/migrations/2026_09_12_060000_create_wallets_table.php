<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->decimal('held_balance', 12, 2)->default(0.00);
            $table->string('currency', 3)->default('RUB');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });

        // Add non-negative check constraints for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_balance_non_negative CHECK (balance >= 0)');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_held_balance_non_negative CHECK (held_balance >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
