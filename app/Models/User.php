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

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;

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

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
