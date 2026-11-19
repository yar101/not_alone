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
        Schema::create('coin_packs', function (Blueprint $table) {
            $table->id();
            $table->decimal('coins', 12, 2);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_starter')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
            $table->index('is_starter');
        });

        // Seed initial default coin packs
        DB::table('coin_packs')->insert([
            [
                'coins' => 100.00,
                'is_visible' => true,
                'is_starter' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'coins' => 300.00,
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'coins' => 500.00,
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'coins' => 1000.00,
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'coins' => 2000.00,
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_packs');
    }
};
