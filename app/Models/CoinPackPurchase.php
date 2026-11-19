<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinPackPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'coin_pack_id',
        'transaction_id',
        'coins',
        'fee_amount',
        'net_amount',
        'is_starter',
    ];

    protected $casts = [
        'coins' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'is_starter' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(CoinPack::class, 'coin_pack_id')->withTrashed();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'transaction_id');
    }
}
