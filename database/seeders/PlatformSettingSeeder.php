<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
         = [
            'order_auto_complete_delay' => '72',
            'rating_low_threshold'      => '30',
            'content_pack_price_min'    => '100',
            'content_pack_price_max'    => '10000',
            'moderate_new_packs'        => '1',
            'moderate_existing_packs'   => '0',
        ];

        foreach ($settings as $key => $value) {
            PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
