<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'idol_id',
        'report_number',
        'period_start',
        'period_end',
        'orders_count',
        'gross_sales',
        'platform_fee_amount',
        'customer_service_fee_amount',
        'net_payout_amount',
        'status',
        'acceptance_deadline',
        'accepted_at',
        'items_breakdown',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'gross_sales' => 'decimal:2',
        'platform_fee_amount' => 'decimal:2',
        'customer_service_fee_amount' => 'decimal:2',
        'net_payout_amount' => 'decimal:2',
        'acceptance_deadline' => 'datetime',
        'accepted_at' => 'datetime',
        'items_breakdown' => 'array',
    ];

    public function idol(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idol_id');
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }
}
