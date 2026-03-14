<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = ['name', 'description', 'name_suggestions', 'image_path', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'name_suggestions' => 'array',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
