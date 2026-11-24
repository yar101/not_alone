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
        // 1. Добавление полей самозанятого и СБП-выплат в таблицу users
        Schema::table('users', function (Blueprint $table) {
            $table->string('tax_status', 20)->default('none')->after('is_idol');
            $table->string('inn', 12)->nullable()->after('tax_status')->index();
            $table->timestamp('tax_verified_at')->nullable()->after('inn');
            $table->string('payout_phone', 20)->nullable()->after('tax_verified_at');
            $table->string('sbp_bank_id', 50)->nullable()->after('payout_phone');
            $table->timestamp('payout_requisites_changed_at')->nullable()->after('sbp_bank_id');
            $table->boolean('is_payout_ready')->default(false)->after('payout_requisites_changed_at');
            $table->timestamp('idol_status_revoked_at')->nullable()->after('is_payout_ready');
        });

        // 2. Создание таблицы chargeback_claims (регрессные требования по ст. 1081 ГК РФ)
        Schema::create('chargeback_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idol_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete()->index();
            $table->decimal('amount', 12, 2);
            $table->decimal('recovered_amount', 12, 2)->default(0.00);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['idol_id', 'status'], 'chargeback_claims_idol_status_idx');
        });

        // 3. Создание таблицы processed_webhook_events (идемпотентность и защита от replay-атак)
        Schema::create('processed_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 100)->unique();
            $table->string('idempotency_key', 100)->nullable()->index();
            $table->string('event_type', 50)->index();
            $table->jsonb('payload')->nullable();
            $table->timestamp('processed_at');
            $table->timestamp('created_at')->useCurrent();
        });

        // 4. Создание таблицы tax_id_cooldowns (кулдаун 365 дней по ИНН)
        Schema::create('tax_id_cooldowns', function (Blueprint $table) {
            $table->id();
            $table->string('inn', 12)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->index();
            $table->timestamp('revoked_at');
            $table->timestamp('cooldown_until')->index();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        // 5. Миграция отрицательных балансов (овердрафтов) в chargeback_claims и обнуление
        $negativeWallets = DB::table('wallets')
            ->where('pink_balance', '<', 0)
            ->orWhere('balance', '<', 0)
            ->get();

        foreach ($negativeWallets as $wallet) {
            $debt = 0.00;
            if ($wallet->pink_balance < 0) {
                $debt += abs((float) $wallet->pink_balance);
            }
            if ($wallet->balance < 0) {
                $debt += abs((float) $wallet->balance);
            }

            if ($debt > 0) {
                DB::table('chargeback_claims')->insert([
                    'idol_id' => $wallet->user_id,
                    'order_id' => null,
                    'amount' => $debt,
                    'recovered_amount' => 0.00,
                    'status' => 'pending',
                    'metadata' => json_encode(['migrated_from_overdraft' => true, 'original_wallet_id' => $wallet->id]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('wallets')->where('id', $wallet->id)->update([
                    'balance' => max(0.00, (float) $wallet->balance),
                    'pink_balance' => max(0.00, (float) $wallet->pink_balance),
                ]);
            }
        }

        // 6. Установка строгих неотрицательных CHECK-констрейнтов в PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_balance_lower_bound');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_pink_balance_lower_bound');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_balance_non_negative');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_pink_balance_non_negative');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_held_balance_non_negative');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_pink_held_balance_non_negative');

            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_balance_non_negative CHECK (balance >= 0.00)');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_pink_balance_non_negative CHECK (pink_balance >= 0.00)');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_held_balance_non_negative CHECK (held_balance >= 0.00)');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_pink_held_balance_non_negative CHECK (pink_held_balance >= 0.00)');
        }

        // 7. Исторический перевод завершённых заказов старше 60 мин в закрытые (Closed, ст. 720 ГК РФ)
        DB::statement("
            UPDATE orders 
            SET status = 'closed', updated_at = NOW()
            WHERE status = 'completed' 
              AND (
                  (completed_at IS NOT NULL AND completed_at < NOW() - INTERVAL '60 minutes')
                  OR (completed_at IS NULL AND updated_at < NOW() - INTERVAL '60 minutes')
              )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_balance_non_negative');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_pink_balance_non_negative');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_held_balance_non_negative');
            DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS check_wallets_pink_held_balance_non_negative');

            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_balance_lower_bound CHECK (balance >= -50000.00)');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_pink_balance_lower_bound CHECK (pink_balance >= -50000.00)');
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_wallets_pink_held_balance_non_negative CHECK (pink_held_balance >= 0.00)');
        }

        Schema::dropIfExists('tax_id_cooldowns');
        Schema::dropIfExists('processed_webhook_events');
        Schema::dropIfExists('chargeback_claims');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tax_status',
                'inn',
                'tax_verified_at',
                'payout_phone',
                'sbp_bank_id',
                'payout_requisites_changed_at',
                'is_payout_ready',
                'idol_status_revoked_at',
            ]);
        });
    }
};
