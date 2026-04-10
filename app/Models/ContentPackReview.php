<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPackReview extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'content_pack_id',
        'admin_id',
        'decision',
        'flagged_fields',
        'field_comments',
        'flagged_photo_ids',
        'photo_comments',
        'cover_photo_id',
        'cover_upload_path',
    ];

    protected $casts = [
        'flagged_fields'    => 'array',
        'field_comments'    => 'array',
        'flagged_photo_ids' => 'array',
        'photo_comments'    => 'array',
        'created_at'        => 'datetime',
    ];

    public function contentPack(): BelongsTo
    {
        return $this->belongsTo(ContentPack::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
