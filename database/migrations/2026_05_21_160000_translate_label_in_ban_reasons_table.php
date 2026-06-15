<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Truncate the table to clear existing incompatible text data
        DB::table('ban_reasons')->truncate();

        Schema::table('ban_reasons', function (Blueprint $table) {
            $table->dropColumn('label');
        });

        Schema::table('ban_reasons', function (Blueprint $table) {
            $table->jsonb('label')->default('{}')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('ban_reasons', function (Blueprint $table) {
            $table->dropColumn('label');
        });

        Schema::table('ban_reasons', function (Blueprint $table) {
            $table->string('label')->after('id');
        });
    }
};
