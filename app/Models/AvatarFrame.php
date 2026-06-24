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
}
