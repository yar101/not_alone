<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLanguage extends Model
{
    protected $fillable = ['user_id', 'language_code'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
