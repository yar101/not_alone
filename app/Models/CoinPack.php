<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoinPack extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coins',
        'is_visible',
        'is_starter',
        'sort_order',
    ];

    protected $casts = [
        'coins' => 'decimal:2',
        'is_visible' => 'boolean',
        'is_starter' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(CoinPackPurchase::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('coins');
    }

    /**
     * @return Collection<int, CoinPack>
     */
    public static function getVisiblePacks(): Collection
    {
        return static::visible()->get();
    }
}
