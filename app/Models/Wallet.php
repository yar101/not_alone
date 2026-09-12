<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'held_balance',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'held_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'total_balance',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest('id');
    }

    public function getTotalBalanceAttribute(): float
    {
        return (float) bcadd((string) $this->balance, (string) $this->held_balance, 2);
    }

    public function hasSufficientBalance(float|string $amount): bool
    {
        return bccomp((string) $this->balance, (string) $amount, 2) >= 0;
    }
}
