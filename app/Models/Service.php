<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $fillable = ['user_id', 'category_id', 'time_unit_id', 'name', 'price', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price'     => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function timeUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceTimeUnit::class, 'time_unit_id');
    }

}
