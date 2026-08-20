<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPackChangeRequest extends Model
{
    protected $fillable = [
        'content_pack_id',
        'changed_fields',
        'pending_title',
        'pending_description',
        'pending_price',
        'flagged_fields',
        'field_comments',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_comment',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'pending_price' => 'integer',
        'flagged_fields' => 'array',
        'field_comments' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function contentPack(): BelongsTo
    {
        return $this->belongsTo(ContentPack::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
