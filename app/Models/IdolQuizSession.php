<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdolQuizSession extends Model
{
    protected $fillable = ['user_id', 'attempt_number', 'status', 'errors_count', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'errors_count' => 'integer',
            'attempt_number' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function questions()
    {
        return $this->hasMany(IdolQuizSessionQuestion::class, 'session_id')->orderBy('stage');
    }

    public function toFrontendData(): array
    {
        $answeredStages = $this->questions()->whereNotNull('answered_at')->pluck('stage')->toArray();
        $nextStage = empty($answeredStages) ? 1 : (max($answeredStages) + 1);
        $question = $this->questions()->where('stage', $nextStage)->with('question')->first();

        return [
            'id' => $this->id,
            'attempt_number' => $this->attempt_number,
            'errors_count' => $this->errors_count,
            'answered_stages' => $answeredStages,
            'current_stage' => $nextStage,
            'current_question' => $question ? [
                'stage' => $question->stage,
                'question_id' => $question->question_id,
                'question' => $question->question->question,
                'options' => $question->question->options,
            ] : null,
            'all_questions' => $this->questions()->with('question')->get()->map(fn($sq) => [
                'stage' => $sq->stage,
                'question_id' => $sq->question_id,
                'question' => $sq->question->question,
                'options' => $sq->question->options,
            ])->toArray(),
        ];
    }
}
