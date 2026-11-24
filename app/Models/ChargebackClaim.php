<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargebackClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'idol_id',
        'order_id',
        'claim_reference',
        'amount',
        'recovered_amount',
        'status',
        'resolved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recovered_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function idol(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idol_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function getRemainingDebtAttribute(): float
    {
        $amount = (float) $this->amount;
        $recovered = (float) $this->recovered_amount;

        return max(0.00, round($amount - $recovered, 2));
    }
}
