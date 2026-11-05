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
        DB::table('service_price_limits')->delete();

        Schema::table('service_price_limits', function (Blueprint $table) {
            $table->dropForeign(['time_unit_id']);
            $table->dropIndex(['time_unit_id']);
            $table->dropColumn('time_unit_id');
            $table->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->unique('category_id');
            $table->unsignedInteger('max_price')->nullable()->change();
            $table->unsignedInteger('low_rating_max_price')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_price_limits', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropUnique(['category_id']);
            $table->dropColumn(['category_id', 'low_rating_max_price']);
            $table->foreignId('time_unit_id')->nullable()->constrained('service_time_units')->cascadeOnDelete();
            $table->index('time_unit_id');
            $table->unsignedInteger('max_price')->nullable(false)->change();
        });
    }
};
