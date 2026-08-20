<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStrike extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'rating_deducted',
        'admin_note',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'rating_deducted' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
