<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceChangeRequest extends Model
{
    protected $fillable = [
        'service_id',
        'changed_fields',
        'pending_name',
        'pending_price',
        'pending_category_id',
        'pending_time_unit_id',
        'status',
        'flagged_fields',
        'field_comments',
        'reviewed_by',
        'reviewed_at',
        'admin_comment',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'pending_name' => 'array', // HasTranslations uses array internally if needed, but we store as json
        'pending_price' => 'integer',
        'pending_category_id' => 'integer',
        'pending_time_unit_id' => 'integer',
        'flagged_fields' => 'array',
        'field_comments' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function pendingCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'pending_category_id');
    }

    public function pendingTimeUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceTimeUnit::class, 'pending_time_unit_id');
    }
}
