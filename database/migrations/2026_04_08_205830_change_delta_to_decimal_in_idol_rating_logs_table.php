<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idol_rating_logs', function (Blueprint $table) {
            $table->decimal('delta', 5, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('idol_rating_logs', function (Blueprint $table) {
            $table->tinyInteger('delta')->change();
        });
    }
};
