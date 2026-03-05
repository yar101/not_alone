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
        Schema::create('idol_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('stage')->comment('1-10');
            $table->text('question');
            $table->json('options');
            $table->tinyInteger('correct_option_index');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idol_quiz_questions');
    }
};
