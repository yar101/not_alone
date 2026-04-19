<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->jsonb('name_tmp')->nullable()->after('name');
        });

        DB::statement("UPDATE services SET name_tmp = jsonb_build_object('ru', name)");

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->renameColumn('name_tmp', 'name');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('name_bak')->nullable()->after('name');
        });

        DB::statement("UPDATE services SET name_bak = name->>'ru'");

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->renameColumn('name_bak', 'name');
        });
    }
};
