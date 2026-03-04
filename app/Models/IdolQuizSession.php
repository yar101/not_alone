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
}
