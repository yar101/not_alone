<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idol_category_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idol_category_descriptions');
    }
};
