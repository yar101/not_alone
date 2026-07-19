<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

class ServiceTimeUnit extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'time_unit_id');
    }

    public function priceLimit(): HasOne
    {
        return $this->hasOne(ServicePriceLimit::class, 'time_unit_id');
    }

    protected static function booted()
    {
        $clearCache = fn() => \Illuminate\Support\Facades\Cache::forget('search_service_time_units');
        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
