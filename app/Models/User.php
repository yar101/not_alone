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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasPushSubscriptions;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'gender',
        'birth_date',
        'about',
        'voice_path',
        'avatar_path',
        'active_frame_id',
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

    public function ordersAsIdol(): HasMany
    {
        return $this->hasMany(Order::class, 'idol_id');
    }

    public function avatarFrames(): BelongsToMany
    {
        return $this->belongsToMany(AvatarFrame::class, 'user_avatar_frames')
            ->withPivot('acquired_at')
            ->withTimestamps();
    }

    public function activeFrame(): BelongsTo
    {
        return $this->belongsTo(AvatarFrame::class, 'active_frame_id');
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

    public function strikes(): HasMany
    {
        return $this->hasMany(UserStrike::class);
    }

    public function activeStrikesCount(): int
    {
        return $this->strikes()->where('expires_at', '>', now())->count();
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

    public function hasUnreadMessages(): bool
    {
        return $this->hasUnreadConversation();
    }

    public function hasUnreadDirect(): bool
    {
        return $this->hasUnreadConversation(orderOnly: false);
    }

    public function hasUnreadOrders(): bool
    {
        return $this->hasUnreadConversation(orderOnly: true);
    }

    public function hasUnreadMine(): bool
    {
        return $this->hasUnreadConversation(orderOnly: true, role: 'customer');
    }

    public function hasUnreadIncoming(): bool
    {
        return $this->hasUnreadConversation(orderOnly: true, role: 'idol');
    }

    private function hasUnreadConversation(?bool $orderOnly = null, ?string $role = null): bool
    {
        return $this->conversationParticipants()
            ->where('has_unread', true)
            ->when($orderOnly === true, fn($q) => $q->whereHas('conversation', fn($c) => $c->whereNotNull('order_id')))
            ->when($orderOnly === false, fn($q) => $q->whereHas('conversation', fn($c) => $c->whereNull('order_id')))
            ->when($role === 'customer', fn($q) => $q->whereHas('conversation', fn($c) => $c->whereHas('order', fn($o) => $o->where('customer_id', $this->id))))
            ->when($role === 'idol',     fn($q) => $q->whereHas('conversation', fn($c) => $c->whereHas('order', fn($o) => $o->where('idol_id', $this->id))))
            ->exists();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function anonymize(): void
    {
        if ($this->avatar_path) {
            Storage::delete($this->avatar_path);
        }
        if ($this->voice_path) {
            Storage::delete($this->voice_path);
        }

        $this->traits()->detach();
        $this->interests()->detach();
        $this->languages()->delete();

        $this->fill([
            'name' => 'Удалённый пользователь',
            'email' => 'deleted_' . $this->id . '@deleted.ru',
            'password' => bcrypt(Str::random(40)),
            'avatar_path' => null,
            'voice_path' => null,
            'about' => null,
            'birth_date' => null,
            'timezone' => null,
            'rating' => 0,
            'is_banned' => false,
            'banned_at' => null,
            'banned_until' => null,
            'ban_reason' => null,
            'banned_by' => null,
            'gender' => null,
        ]);

        $this->save();
    }
}
