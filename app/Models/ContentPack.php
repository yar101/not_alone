<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class ContentPack extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'price',
        'status',
        'cover_path',
        'published_at',
        'moderated_by',
        'moderated_at',
        'resubmitted_at',
    ];

    protected $casts = [
        'price'           => 'integer',
        'published_at'    => 'datetime',
        'moderated_at'    => 'datetime',
        'resubmitted_at'  => 'datetime',
    ];

    protected $appends = ['cover_url'];

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->cover_path) {
            return Storage::url($this->cover_path);
        }
        $first = $this->photos->first();
        return $first ? $first->url : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ContentPackPhoto::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ContentPackReview::class)->latest('created_at');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(ContentPackReview::class)->latestOfMany('created_at');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ContentPackPurchase::class);
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'moderated_by');
    }

    public function pendingChangeRequest(): HasOne
    {
        return $this->hasOne(ContentPackChangeRequest::class)->whereIn('status', ['pending', 'has_remarks']);
    }
}
