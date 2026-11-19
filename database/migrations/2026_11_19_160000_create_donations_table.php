<?php

use App\Models\PlatformSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('idol_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_charged', 12, 2);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->boolean('fee_covered_by_donor')->default(false);
            $table->text('message')->nullable();
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();

            $table->index(['idol_id', 'created_at']);
            $table->index(['donor_id', 'created_at']);
        });

        // Seed default platform settings for donations if not set
        $defaultSettings = [
            'donation_min_amount' => '1',
            'donation_max_amount' => '15000',
            'donation_daily_limit' => '10',
        ];

        foreach ($defaultSettings as $key => $value) {
            PlatformSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');

        PlatformSetting::whereIn('key', [
            'donation_min_amount',
            'donation_max_amount',
            'donation_daily_limit',
        ])->delete();
    }
};
