<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    protected $fillable = [
        'reviewer_id',
        'idol_id',
        'order_id',
        'rating',
        'text',
        'services_snapshot',
        'is_hidden',
    ];

    protected $casts = [
        'services_snapshot' => 'array',
        'is_hidden' => 'boolean',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')->withTrashed();
    }

    public function idol(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idol_id')->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function epithets(): BelongsToMany
    {
        return $this->belongsToMany(ReviewEpithet::class, 'review_epithet_review');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(ReviewDispute::class);
    }
}
