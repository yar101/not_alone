<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class InterestCategory extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = ['name', 'sort_order'];

    public function interests(): HasMany
    {
        return $this->hasMany(Interest::class, 'category_id')->orderBy('sort_order');
    }

    protected static function booted()
    {
        $clearCache = fn() => \Illuminate\Support\Facades\Cache::forget('search_interest_categories');
        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
