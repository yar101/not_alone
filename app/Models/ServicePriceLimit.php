<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePriceLimit extends Model
{
    protected $fillable = [
        'category_id',
        'max_price',
        'low_rating_max_price',
    ];

    protected function casts(): array
    {
        return [
            'max_price' => 'integer',
            'low_rating_max_price' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }
}
