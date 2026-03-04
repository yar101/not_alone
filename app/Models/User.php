<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'pinned_body',
        'pinned_photo_path',
        'timezone',
        'profile_checklist_snoozed_until',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['age', 'avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'profile_checklist_snoozed_until' => 'datetime',
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

    public function getPinnedPhotoUrlAttribute(): ?string
    {
        return $this->pinned_photo_path ? Storage::url($this->pinned_photo_path) : null;
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

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
