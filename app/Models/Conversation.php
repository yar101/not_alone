<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'is_support', 'closed_at', 'admin_read_at'];

    protected $casts = [
        'is_support'    => 'boolean',
        'closed_at'     => 'datetime',
        'admin_read_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }


    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')->withTrashed();
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function unreadCountFor(User $user): int
    {
        $participant = $this->participants->firstWhere('user_id', $user->id);
        if (! $participant) {
            return 0;
        }

        $query = $this->messages()->where(function ($q) use ($user) {
            $q->whereNull('sender_id')->orWhere('sender_id', '!=', $user->id);
        });
        if ($participant->last_read_at) {
            $query->where('created_at', '>', $participant->last_read_at);
        }

        return $query->count();
    }

    public static function findOrCreateBetween(User $a, User $b): self
    {
        $conversation = self::whereNull('order_id')
            ->whereHas('participants', function ($q) use ($a) {
                $q->where('user_id', $a->id);
            })->whereHas('participants', function ($q) use ($b) {
                $q->where('user_id', $b->id);
            })->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = self::create();
        $conversation->participants()->createMany([
            ['user_id' => $a->id],
            ['user_id' => $b->id],
        ]);

        return $conversation;
    }

    public function scopeWithUser($query, int $userId)
    {
        return $query->whereHas('participants', fn($q) => $q->where('user_id', $userId));
    }

    public function scopeSearch($query, string $search, int $userId)
    {
        $like = '%' . $search . '%';
        return $query->where(function ($q) use ($userId, $like) {
            $q->where('is_support', true)
              ->orWhereHas('participants', function ($pq) use ($userId, $like) {
                  $pq->where('user_id', '!=', $userId)
                     ->whereHas('user', fn($uq) => $uq->whereRaw('LOWER(name) LIKE LOWER(?)', [$like]));
              });
        });
    }

    public function scopeUnreadForUser($query, int $userId)
    {
        return $query->whereHas('participants', fn($q) => $q->where('user_id', $userId)->where('has_unread', true));
    }
}
