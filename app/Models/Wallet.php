<?php

namespace App\Models;

use App\Events\WalletBalanceUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updated(function (Wallet $wallet) {
            if ($wallet->wasChanged(['balance', 'held_balance', 'pink_balance', 'pink_held_balance'])) {
                $isIdol = (bool) ($wallet->user?->is_idol ?? false);

                WalletBalanceUpdated::dispatch(
                    $wallet->user_id,
                    (float) $wallet->balance,
                    (float) $wallet->held_balance,
                    $wallet->total_balance,
                    $isIdol ? (float) $wallet->pink_balance : null,
                    $isIdol ? (float) $wallet->pink_held_balance : null,
                    $isIdol ? $wallet->pink_total_balance : null,
                );
            }
        });
    }

    protected $fillable = [
        'user_id',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'held_balance' => 'decimal:2',
        'pink_balance' => 'decimal:2',
        'pink_held_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'total_balance',
        'pink_total_balance',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $isIdol = $this->relationLoaded('user')
            ? (bool) ($this->user?->is_idol ?? false)
            : ($this->user_id === auth('web')->id() ? (bool) (auth('web')->user()?->is_idol ?? false) : (bool) ($this->user?->is_idol ?? false));
        if (! $isIdol) {
            unset($array['pink_balance'], $array['pink_held_balance'], $array['pink_total_balance']);
        }

        return $array;
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest('id');
    }

    public function getTotalBalanceAttribute(): float
    {
        return (float) bcadd((string) $this->balance, (string) $this->held_balance, 2);
    }

    public function getPinkTotalBalanceAttribute(): float
    {
        return (float) bcadd((string) $this->pink_balance, (string) $this->pink_held_balance, 2);
    }

    public function hasSufficientBalance(float|string $amount): bool
    {
        return bccomp((string) $this->balance, (string) $amount, 2) >= 0;
    }

    public function hasSufficientPinkBalance(float|string $amount): bool
    {
        return bccomp((string) $this->pink_balance, (string) $amount, 2) >= 0;
    }
}
