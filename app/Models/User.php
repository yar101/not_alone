<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasPushSubscriptions;

    protected $fillable = [
        'name',
        'gender',
        'birth_date',
        'about',
        'voice_path',
        'avatar_path',
        'timezone',
        'profile_checklist_snoozed_until',
        'email',
        'locale',
        'password',
        'is_idol',
        'rating',
        'idol_quiz_cooldown_until',
        'idol_quiz_passed_at',
        'is_banned',
        'banned_at',
        'banned_until',
        'ban_reason',
        'banned_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['age', 'avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at'              => 'datetime',
            'password'                       => 'hashed',
            'birth_date'                     => 'date',
            'profile_checklist_snoozed_until'=> 'datetime',
            'is_idol'                        => 'boolean',
            'idol_quiz_cooldown_until'       => 'datetime',
            'idol_quiz_passed_at'            => 'datetime',
            'rating'                         => 'integer',
            'is_banned'                      => 'boolean',
            'banned_at'                      => 'datetime',
            'banned_until'                   => 'datetime',
        ];
    }

    public function isActiveBanned(): bool
    {
        if (!$this->is_banned) return false;
        return $this->banned_until === null || $this->banned_until->isFuture();
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? Carbon::parse($this->birth_date)->age : null;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::url($this->avatar_path) : null;
    }

    public function traits(): BelongsToMany
    {
        return $this->belongsToMany(PersonalityTrait::class, 'user_traits', 'user_id', 'trait_id')
            ->orderBy('sort_order');
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'user_interests', 'user_id', 'interest_id')
            ->with('category')
            ->orderBy('sort_order');
    }

    public function languages(): HasMany
    {
        return $this->hasMany(UserLanguage::class)->orderBy('language_code');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class)->latest();
    }

    public function idolQuizSessions(): HasMany
    {
        return $this->hasMany(IdolQuizSession::class);
    }

    public function idolApplication()
    {
        return $this->hasOne(IdolApplication::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function ratingLogs(): HasMany
    {
        return $this->hasMany(IdolRatingLog::class);
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'banned_by');
    }

    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function chatBlocksGiven(): HasMany
    {
        return $this->hasMany(ChatBlock::class, 'blocker_id');
    }

    public function chatBlocksReceived(): HasMany
    {
        return $this->hasMany(ChatBlock::class, 'blocked_id');
    }

    public function contentPacks(): HasMany
    {
        return $this->hasMany(ContentPack::class);
    }

    public function contentPackPurchases(): HasMany
    {
        return $this->hasMany(ContentPackPurchase::class);
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'idol_id')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'idol_id', 'follower_id')->withTimestamps();
    }

    public function follow(int $userId): void
    {
        $this->following()->syncWithoutDetaching([$userId]);
    }

    public function unfollow(int $userId): void
    {
        $this->following()->detach($userId);
    }

    public function isFollowing(int $userId): bool
    {
        return $this->following()->where('idol_id', $userId)->exists();
    }

    public function unreadMessagesCount(): int
    {
        return $this->unreadConversationCount();
    }

    public function unreadDirectCount(): int
    {
        return $this->unreadConversationCount(orderOnly: false);
    }

    public function unreadOrdersCount(): int
    {
        return $this->unreadConversationCount(orderOnly: true);
    }

    public function unreadMineCount(): int
    {
        return $this->unreadConversationCount(orderOnly: true, role: 'customer');
    }

    public function unreadIncomingCount(): int
    {
        return $this->unreadConversationCount(orderOnly: true, role: 'idol');
    }

    private function unreadConversationCount(?bool $orderOnly = null, ?string $role = null): int
    {
        $participants = $this->conversationParticipants()
            ->when($orderOnly === true, fn($q) => $q->whereHas('conversation', fn($c) => $c->whereNotNull('order_id')))
            ->when($orderOnly === false, fn($q) => $q->whereHas('conversation', fn($c) => $c->whereNull('order_id')))
            ->when($role === 'customer', fn($q) => $q->whereHas('conversation', fn($c) => $c->whereHas('order', fn($o) => $o->where('customer_id', $this->id))))
            ->when($role === 'idol',     fn($q) => $q->whereHas('conversation', fn($c) => $c->whereHas('order', fn($o) => $o->where('idol_id', $this->id))))
            ->get();

        return $participants->sum(function ($participant) {
            $query = Message::where('conversation_id', $participant->conversation_id)
                ->where(function ($q) {
                    $q->whereNull('sender_id')
                      ->orWhere('sender_id', '!=', $this->id);
                });
            if ($participant->last_read_at) {
                $query->where('created_at', '>', $participant->last_read_at);
            }
            return $query->count();
        });
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
