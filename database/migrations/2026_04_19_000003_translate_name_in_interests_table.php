<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interests', function (Blueprint $table) {
            $table->jsonb('name_tmp')->nullable()->after('name_ru');
        });

        DB::statement("UPDATE interests SET name_tmp = jsonb_build_object('ru', name_ru)");

        Schema::table('interests', function (Blueprint $table) {
            $table->jsonb('name_tmp')->nullable(false)->default('{}')->change();
            $table->dropColumn('name_ru');
        });

        Schema::table('interests', function (Blueprint $table) {
            $table->renameColumn('name_tmp', 'name');
        });
    }

    public function down(): void
    {
        Schema::table('interests', function (Blueprint $table) {
            $table->string('name_ru_tmp', 100)->nullable()->after('name');
        });

        DB::statement("UPDATE interests SET name_ru_tmp = name->>'ru'");

        Schema::table('interests', function (Blueprint $table) {
            $table->string('name_ru_tmp', 100)->nullable(false)->default('')->change();
            $table->dropColumn('name');
        });

        Schema::table('interests', function (Blueprint $table) {
            $table->renameColumn('name_ru_tmp', 'name_ru');
        });
    }
};
