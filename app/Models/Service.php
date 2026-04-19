<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Service extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = ['user_id', 'category_id', 'time_unit_id', 'price', 'is_active', 'status', 'rejection_reason', 'moderated_by', 'moderated_at'];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'price'        => 'integer',
            'moderated_at' => 'datetime',
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

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'moderated_by');
    }
}
