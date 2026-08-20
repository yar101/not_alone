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
        'status', 'cancel_reason', 'cancelled_by',
        'completion_confirmed_by_idol', 'completion_confirmed_by_customer',
        'paid_at', 'completed_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
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
}
