<?php

use App\Models\PlatformSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            'refund_gateway_fee_percent' => '4.0',
            'refund_platform_fee_percent' => '10.0',
        ];

        foreach ($settings as $key => $value) {
            if (! PlatformSetting::where('key', $key)->exists()) {
                PlatformSetting::create([
                    'key' => $key,
                    'value' => $value,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        PlatformSetting::whereIn('key', [
            'refund_gateway_fee_percent',
            'refund_platform_fee_percent',
        ])->delete();
    }
};
