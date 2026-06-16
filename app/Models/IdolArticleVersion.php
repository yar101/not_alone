<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdolArticleVersion extends Model
{
    protected $fillable = ['html', 'label', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUnlabeled($query)
    {
        return $query->whereNull('label');
    }

    /**
     * Make this version the active one (deactivates all others).
     */
    public function activate(): void
    {
        static::query()->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /**
     * Purge old unlabeled versions, keeping only the latest $keep.
     * Never deletes the active version.
     */
    public static function purgeUnlabeled(int $keep = 10): void
    {
        $ids = static::unlabeled()
            ->orderByDesc('created_at')
            ->skip($keep)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            static::whereIn('id', $ids)->where('is_active', false)->delete();
        }
    }
}
