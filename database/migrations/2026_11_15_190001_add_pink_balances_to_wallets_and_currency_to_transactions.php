<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('pink_balance', 12, 2)->default(0.00)->after('held_balance');
            $table->decimal('pink_held_balance', 12, 2)->default(0.00)->after('pink_balance');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('currency', 10)->default('blue')->after('type');
            $table->index(['wallet_id', 'currency', 'created_at'], 'wallet_tx_currency_idx');
        });

        // Historical data migration: move idol balances to pink accounts BEFORE applying CHECK constraints
        DB::statement('
            UPDATE wallets 
            SET pink_balance = balance, balance = 0.00 
            WHERE user_id IN (SELECT id FROM users WHERE is_idol = true) AND balance <> 0
        ');

        DB::statement('
            UPDATE wallets 
            SET pink_held_balance = held_balance, held_balance = 0.00 
            WHERE user_id IN (SELECT id FROM users WHERE is_idol = true) AND held_balance <> 0
        ');

        DB::statement("
            UPDATE wallet_transactions 
            SET currency = 'pink' 
            WHERE type IN ('order_payout', 'pack_sale', 'withdrawal', 'order_clawback', 'order_hold_cancelled')
        ");

        if (DB::getDriverName() === 'pgsql') {
            // Wallets lower bound constraint allows down to -50 000.00
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_balance_lower_bound');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_balance_lower_bound CHECK (balance >= -50000.00)');

            // Pink balance allows idol overdraft down to -50 000.00
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_pink_balance_lower_bound CHECK (pink_balance >= -50000.00)');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_pink_held_balance_non_negative CHECK (pink_held_balance >= 0.00)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_balance_lower_bound');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_pink_balance_lower_bound');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_pink_held_balance_non_negative');
        }

        // Migrate pink_balance back to balance for idols
        DB::statement('
            UPDATE wallets 
            SET balance = pink_balance, pink_balance = 0.00 
            WHERE user_id IN (SELECT id FROM users WHERE is_idol = true) AND pink_balance <> 0
        ');

        DB::statement('
            UPDATE wallets 
            SET held_balance = pink_held_balance, pink_held_balance = 0.00 
            WHERE user_id IN (SELECT id FROM users WHERE is_idol = true) AND pink_held_balance <> 0
        ');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_balance_lower_bound CHECK (balance >= -50000.00)');
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wallet_tx_currency_idx');
            $table->dropColumn('currency');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['pink_balance', 'pink_held_balance']);
        });
    }
};
