<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReviewEpithet extends Model
{
    protected $fillable = ['label', 'sort_order'];

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn($q) => $q->orderBy('sort_order'));
    }

    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_epithet_review');
    }
}
