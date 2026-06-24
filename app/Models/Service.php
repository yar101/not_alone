<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

class Service extends Model
{
    use HasTranslations, SoftDeletes;

    public array $translatable = ['name'];

    protected $fillable = ['name', 'user_id', 'category_id', 'time_unit_id', 'price', 'is_active', 'status', 'rejection_reason', 'moderated_by', 'moderated_at', 'resubmitted_at'];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'price'          => 'integer',
            'moderated_at'   => 'datetime',
            'resubmitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
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

    public function reviews(): HasMany
    {
        return $this->hasMany(ServiceReview::class);
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(ServiceReview::class)->latestOfMany();
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ServiceChangeRequest::class);
    }

    public function pendingChangeRequest(): HasOne
    {
        return $this->hasOne(ServiceChangeRequest::class)->whereIn('status', ['pending', 'has_remarks', 'rejected']);
    }
}
