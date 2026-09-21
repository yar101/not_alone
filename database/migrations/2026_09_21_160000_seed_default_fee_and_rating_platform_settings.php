<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $defaultSettings = [
            // Standard fees
            ['key' => 'deposit_fee_percent', 'value' => '4'],
            ['key' => 'withdrawal_fee_percent', 'value' => '4'],
            ['key' => 'platform_fee_percent', 'value' => '10'],

            // Core system parameters
            ['key' => 'order_auto_complete_delay', 'value' => '72'],
            ['key' => 'rating_low_threshold', 'value' => '30'],
            ['key' => 'content_pack_price_min', 'value' => '100'],
            ['key' => 'content_pack_price_max', 'value' => '10000'],
            ['key' => 'moderate_new_packs', 'value' => '1'],
            ['key' => 'moderate_existing_packs', 'value' => '0'],

            // Rating deltas
            ['key' => 'rating_delta_review_5star', 'value' => '0.8'],
            ['key' => 'rating_delta_review_4star', 'value' => '0.4'],
            ['key' => 'rating_delta_review_2star', 'value' => '-0.5'],
            ['key' => 'rating_delta_review_1star', 'value' => '-1.2'],
            ['key' => 'rating_delta_order_completed', 'value' => '0.2'],
            ['key' => 'rating_delta_report_accepted', 'value' => '-2'],
            ['key' => 'rating_delta_review_dispute_approved', 'value' => '0.6'],
        ];

        foreach ($defaultSettings as $setting) {
            DB::table('platform_settings')->insertOrIgnore([
                'key' => $setting['key'],
                'value' => $setting['value'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('platform_settings')->whereIn('key', [
            'deposit_fee_percent',
            'withdrawal_fee_percent',
            'platform_fee_percent',
            'order_auto_complete_delay',
            'rating_low_threshold',
            'content_pack_price_min',
            'content_pack_price_max',
            'moderate_new_packs',
            'moderate_existing_packs',
            'rating_delta_review_5star',
            'rating_delta_review_4star',
            'rating_delta_review_2star',
            'rating_delta_review_1star',
            'rating_delta_order_completed',
            'rating_delta_report_accepted',
            'rating_delta_review_dispute_approved',
        ])->delete();
    }
};
