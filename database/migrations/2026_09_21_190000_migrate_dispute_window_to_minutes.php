<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('platform_settings')->where('key', 'order_dispute_window_hours')->first();

        $minutes = 60;
        if ($existing && is_numeric($existing->value)) {
            $minutes = max(1, (int) round(((float) $existing->value) * 60));
        }

        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'order_dispute_window_minutes'],
            ['value' => (string) $minutes, 'updated_at' => now()]
        );

        DB::table('platform_settings')->where('key', 'order_dispute_window_hours')->delete();
    }

    public function down(): void
    {
        $existing = DB::table('platform_settings')->where('key', 'order_dispute_window_minutes')->first();

        $hours = 1.0;
        if ($existing && is_numeric($existing->value)) {
            $hours = round(((int) $existing->value) / 60, 2);
        }

        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'order_dispute_window_hours'],
            ['value' => (string) $hours, 'updated_at' => now()]
        );

        DB::table('platform_settings')->where('key', 'order_dispute_window_minutes')->delete();
    }
};
