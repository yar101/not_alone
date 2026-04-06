<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->string('category')->nullable()->after('image');
            $table->text('excerpt')->nullable()->after('category');
            $table->boolean('is_pinned')->default(false)->after('excerpt');
            $table->unsignedInteger('views_count')->default(0)->after('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['category', 'excerpt', 'is_pinned', 'views_count']);
        });
    }
};
