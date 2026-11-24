<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxIdCooldown extends Model
{
    use HasFactory;

    protected $fillable = [
        'inn',
        'user_id',
        'revoked_at',
        'cooldown_until',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
            'cooldown_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnderCooldown(): bool
    {
        return $this->cooldown_until && $this->cooldown_until->isFuture();
    }
}
