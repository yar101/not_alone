<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_id',
        'idol_id',
        'amount',
        'amount_charged',
        'platform_fee',
        'fee_covered_by_donor',
        'message',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_charged' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'fee_covered_by_donor' => 'boolean',
    ];

    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_id')->withTrashed();
    }

    public function idol(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idol_id')->withTrashed();
    }

    public function walletTransactions(): MorphMany
    {
        return $this->morphMany(WalletTransaction::class, 'reference');
    }
}
