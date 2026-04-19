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
            $table->jsonb('description_tmp')->nullable()->after('description');
        });

        DB::statement("UPDATE service_categories SET description_tmp = jsonb_build_object('ru', description) WHERE description IS NOT NULL");

        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->renameColumn('description_tmp', 'description');
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->text('description_tmp')->nullable()->after('description');
        });

        DB::statement("UPDATE service_categories SET description_tmp = description->>'ru' WHERE description IS NOT NULL");

        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->renameColumn('description_tmp', 'description');
        });
    }
};
