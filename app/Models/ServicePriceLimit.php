<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePriceLimit extends Model
{
    protected $fillable = ['time_unit_id', 'max_price'];

    public function timeUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceTimeUnit::class, 'time_unit_id');
    }
}
