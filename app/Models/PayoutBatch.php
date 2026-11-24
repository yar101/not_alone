<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'status',
        'total_amount',
        'total_recipients',
        'successful_count',
        'failed_count',
        'is_automated',
        'scheduled_for',
        'executed_at',
        'approved_by',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_recipients' => 'integer',
        'successful_count' => 'integer',
        'failed_count' => 'integer',
        'is_automated' => 'boolean',
        'scheduled_for' => 'datetime',
        'executed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayoutBatchItem::class, 'payout_batch_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }
}
