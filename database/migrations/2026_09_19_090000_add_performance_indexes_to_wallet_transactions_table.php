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
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->index(['wallet_id', 'id'], 'wallet_tx_wallet_id_id_idx');
            $table->index(['wallet_id', 'type', 'id'], 'wallet_tx_wallet_type_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wallet_tx_wallet_id_id_idx');
            $table->dropIndex('wallet_tx_wallet_type_id_idx');
        });
    }
};
