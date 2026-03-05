<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdolQuizQuestion extends Model
{
    protected $fillable = ['stage', 'question', 'options', 'correct_option_index', 'sort_order'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'stage' => 'integer',
            'correct_option_index' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
