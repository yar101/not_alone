<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->jsonb('name_tmp')->nullable()->after('name');
        });

        DB::statement("UPDATE service_categories SET name_tmp = jsonb_build_object('ru', name)");

        Schema::table('service_categories', function (Blueprint $table) {
            $table->jsonb('name_tmp')->nullable(false)->default('{}')->change();
            $table->dropColumn('name');
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->renameColumn('name_tmp', 'name');
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('name_old', 100)->nullable()->after('name');
        });

        DB::statement("UPDATE service_categories SET name_old = name->>'ru'");

        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('name_old', 100)->nullable(false)->default('')->change();
            $table->dropColumn('name');
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->renameColumn('name_old', 'name');
        });
    }
};
