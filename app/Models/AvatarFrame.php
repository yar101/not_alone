<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvatarFrame extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image_path',
        'type', // 'free', 'paid', 'achievement'
        'price',
        'condition_class',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? \Illuminate\Support\Facades\Storage::url($this->image_path) : null;
    }
}
