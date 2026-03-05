<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdolQuizSessionQuestion extends Model
{
    protected $fillable = ['session_id', 'question_id', 'stage', 'user_answer_index', 'is_correct', 'answered_at'];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
            'user_answer_index' => 'integer',
            'stage' => 'integer',
        ];
    }

    public function session()
    {
        return $this->belongsTo(IdolQuizSession::class);
    }

    public function question()
    {
        return $this->belongsTo(IdolQuizQuestion::class);
    }
}
