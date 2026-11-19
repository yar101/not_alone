<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Возврат всегда составляет 100% холда (AGENTS.md §3.3), комиссии при возврате не существуют.
     */
    public function up(): void
    {
        DB::table('platform_settings')
            ->whereIn('key', ['refund_gateway_fee_percent', 'refund_platform_fee_percent'])
            ->delete();
    }

    public function down(): void
    {
        $settings = [
            'refund_gateway_fee_percent' => '4.0',
            'refund_platform_fee_percent' => '10.0',
        ];

        foreach ($settings as $key => $value) {
            if (! DB::table('platform_settings')->where('key', $key)->exists()) {
                DB::table('platform_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
