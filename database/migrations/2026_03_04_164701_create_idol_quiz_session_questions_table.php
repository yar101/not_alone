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
        Schema::create('idol_quiz_session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('idol_quiz_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('idol_quiz_questions')->cascadeOnDelete();
            $table->tinyInteger('stage');
            $table->tinyInteger('user_answer_index')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->index(['session_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idol_quiz_session_questions');
    }
};
