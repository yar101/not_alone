<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add locale to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->default('ru')->after('email');
        });

        // 2. Convert admin_broadcasts fields to jsonb
        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->jsonb('title_tmp')->nullable()->after('title');
            $table->jsonb('body_tmp')->nullable()->after('body');
        });

        DB::statement("UPDATE admin_broadcasts SET title_tmp = jsonb_build_object('ru', title), body_tmp = jsonb_build_object('ru', body)");

        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->dropColumn(['title', 'body']);
        });

        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->renameColumn('title_tmp', 'title');
            $table->renameColumn('body_tmp', 'body');
        });
    }

    public function down(): void
    {
        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->string('title_tmp')->nullable()->after('title');
            $table->text('body_tmp')->nullable()->after('body');
        });

        DB::statement("UPDATE admin_broadcasts SET title_tmp = title->>'ru', body_tmp = body->>'ru'");

        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->dropColumn(['title', 'body']);
        });

        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->renameColumn('title_tmp', 'title');
            $table->renameColumn('body_tmp', 'body');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
