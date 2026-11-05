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
        // 1) В PostgreSQL: ограничение минимального баланса (нижняя граница овердрафта)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_balance_lower_bound');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_balance_lower_bound CHECK (balance >= -50000.00)');
        }

        // 2) В таблице wallets: удалить избыточный дублирующий обычный B-Tree индекс на user_id
        if (Schema::hasIndex('wallets', 'wallets_user_id_index')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->dropIndex('wallets_user_id_index');
            });
        }

        // 3) В таблице wallet_transactions: добавить составные индексы для частых эскроу-выборок и аналитики
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->index(['reference_type', 'reference_id', 'type', 'status', 'id'], 'wallet_tx_escrow_lookup_idx');
            $table->index(['type', 'status'], 'wallet_tx_type_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1) В PostgreSQL: удалить ограничение
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_balance_lower_bound');
        }

        // 2) В таблице wallet_transactions: удалить составные индексы
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasIndex('wallet_transactions', 'wallet_tx_escrow_lookup_idx')) {
                $table->dropIndex('wallet_tx_escrow_lookup_idx');
            }
            if (Schema::hasIndex('wallet_transactions', 'wallet_tx_type_status_idx')) {
                $table->dropIndex('wallet_tx_type_status_idx');
            }
        });

        // 3) В таблице wallets: восстановить индекс wallets_user_id_index
        if (! Schema::hasIndex('wallets', 'wallets_user_id_index')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->index('user_id', 'wallets_user_id_index');
            });
        }
    }
};
