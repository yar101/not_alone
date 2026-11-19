<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPackPurchase extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'content_pack_id',
        'user_id',
        'price_paid',
        'amount_charged',
        'fee_covered_platform',
        'fee_covered_withdrawal',
        'purchased_at',
        'viewed_at',
    ];

    protected $casts = [
        'amount_charged' => 'float',
        'fee_covered_platform' => 'boolean',
        'fee_covered_withdrawal' => 'boolean',
        'purchased_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    public function getPricePaidAttribute($value): float|int
    {
        if ($value === null) {
            return 0;
        }

        return (float) $value == (int) $value ? (int) $value : (float) $value;
    }

    public function contentPack(): BelongsTo
    {
        return $this->belongsTo(ContentPack::class)->withTrashed()->with('user');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function walletTransactions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(WalletTransaction::class, 'reference');
    }
}
