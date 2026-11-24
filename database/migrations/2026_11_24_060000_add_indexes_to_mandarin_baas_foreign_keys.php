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
        Schema::table('chargeback_claims', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('tax_id_cooldowns', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chargeback_claims', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('tax_id_cooldowns', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
