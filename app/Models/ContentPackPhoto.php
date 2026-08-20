<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContentPackPhoto extends Model
{
    protected $fillable = [
        'content_pack_id',
        'path',
        'original_filename',
        'sort_order',
    ];

    public function getUrlAttribute(): string
    {
        try {
            return Storage::disk(config('filesystems.default'))->temporaryUrl($this->path, now()->addMinutes(60));
        } catch (\Throwable) {
            return Storage::disk(config('filesystems.default'))->url($this->path);
        }
    }

    public function contentPack(): BelongsTo
    {
        return $this->belongsTo(ContentPack::class);
    }
}
