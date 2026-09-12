<?php

namespace App\Models;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'held_balance_before',
        'held_balance_after',
        'status',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'description',
        'metadata',
    ];

    protected $casts = [
        'type' => WalletTransactionType::class,
        'status' => WalletTransactionStatus::class,
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'held_balance_before' => 'decimal:2',
        'held_balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
