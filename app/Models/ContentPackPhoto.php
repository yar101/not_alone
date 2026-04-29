<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ContentPackPhoto extends Model
{
    protected $fillable = [
        'content_pack_id',
        'path',
        'original_filename',
        'sort_order',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return URL::temporarySignedRoute('media.serve', now()->addMinutes(60), ['path' => $this->path]);
    }

    public function contentPack(): BelongsTo
    {
        return $this->belongsTo(ContentPack::class);
    }
}
