<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payout_batch_id',
        'idol_id',
        'wallet_transaction_id',
        'amount',
        'phone',
        'sbp_bank_id',
        'status',
        'failure_reason',
        'gateway_operation_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'payout_batch_id');
    }

    public function idol(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idol_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
