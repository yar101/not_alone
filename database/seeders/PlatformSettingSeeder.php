<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'order_auto_complete_delay' => '72',
            'order_dispute_window_minutes' => '60',
            'rating_low_threshold' => '30',
            'content_pack_price_min' => '100',
            'content_pack_price_max' => '10000',
            'moderate_new_packs' => '1',
            'moderate_existing_packs' => '0',
            'deposit_fee_percent' => '4',
            'withdrawal_fee_percent' => '4',
            'platform_fee_percent' => '10',
            'rating_delta_review_5star' => '0.8',
            'rating_delta_review_4star' => '0.4',
            'rating_delta_review_2star' => '-0.5',
            'rating_delta_review_1star' => '-1.2',
            'rating_delta_order_completed' => '0.2',
            'rating_delta_report_accepted' => '-2',
            'rating_delta_review_dispute_approved' => '0.6',
        ];

        foreach ($settings as $key => $value) {
            PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
