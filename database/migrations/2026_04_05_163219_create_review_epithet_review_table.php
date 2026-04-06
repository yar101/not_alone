<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('review_epithet_review', function (Blueprint $table) {
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('review_epithet_id')->constrained('review_epithets')->cascadeOnDelete();
            $table->primary(['review_id', 'review_epithet_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_epithet_review');
    }
};
