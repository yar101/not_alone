<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterestCategory extends Model
{
    protected $fillable = ['name_ru', 'sort_order'];

    public function interests(): HasMany
    {
        return $this->hasMany(Interest::class, 'category_id')->orderBy('sort_order');
    }
}
