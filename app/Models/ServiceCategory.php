<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ServiceCategory extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description', 'name_suggestions', 'image_path', 'accent_color', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'name_suggestions' => 'array',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    protected static function booted()
    {
        $clearCache = function() {
            \Illuminate\Support\Facades\Cache::forget('search_service_categories');
            \Illuminate\Support\Facades\Cache::forget('profile_service_categories');
        };
        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
