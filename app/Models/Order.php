<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'idol_id', 'conversation_id',
        'status', 'customer_service_fee', 'customer_service_fee_percent',
        'fee_covered_platform', 'fee_covered_withdrawal', 'idol_fee_covered',
        'cancel_reason', 'cancelled_by',
        'completion_confirmed_by_idol', 'completion_confirmed_by_customer',
        'paid_at', 'completed_at', 'payout_released_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'customer_service_fee' => 'float',
        'customer_service_fee_percent' => 'float',
        'fee_covered_platform' => 'boolean',
        'fee_covered_withdrawal' => 'boolean',
        'idol_fee_covered' => 'float',
        'completion_confirmed_by_idol' => 'boolean',
        'completion_confirmed_by_customer' => 'boolean',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'payout_released_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function idol(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idol_id')->withTrashed();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(OrderDispute::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('created_at');
    }

    public function logStatusChange(?string $from, string $to, string $actorType, int $actorId, ?string $note = null): void
    {
        $this->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $note,
        ]);
    }

    public function getTotalPriceAttribute(): float
    {
        if ($this->relationLoaded('items')) {
            return (float) $this->items->sum(function ($item) {
                $price = $item->price ?? $item->service?->price ?? 0;

                return $price * ($item->quantity ?? 1);
            });
        }

        return (float) $this->items()
            ->leftJoin('services', 'order_items.service_id', '=', 'services.id')
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(order_items.price, services.price, 0) * COALESCE(order_items.quantity, 1)'));
    }

    public function getTotalWithFeeAttribute(): float
    {
        $base = bcadd((string) $this->total_price, (string) ($this->customer_service_fee ?? 0.0), 2);

        return (float) bcadd($base, (string) ($this->idol_fee_covered ?? 0.0), 2);
    }

    public function getHeldAmountAttribute(): float
    {
        $hold = $this->walletTransactions()
            ->where('type', \App\Enums\WalletTransactionType::OrderHold)
            ->where('status', \App\Enums\WalletTransactionStatus::Completed)
            ->latest('id')
            ->first();

        return $hold ? abs((float) $hold->amount) : (float) $this->total_price;
    }

    public function walletTransactions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(WalletTransaction::class, 'reference');
    }
}
