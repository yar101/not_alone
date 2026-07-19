<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class PersonalityTrait extends Model
{
    use HasTranslations;

    protected $table = 'traits';

    public array $translatable = ['name'];

    protected $fillable = ['name', 'sort_order'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_traits', 'trait_id', 'user_id');
    }

    protected static function booted()
    {
        $clearCache = fn() => \Illuminate\Support\Facades\Cache::forget('search_traits');
        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
