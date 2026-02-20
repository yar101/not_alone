<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PersonalityTrait extends Model
{
    protected $table = 'traits';

    protected $fillable = ['name_ru', 'sort_order'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_traits', 'trait_id', 'user_id');
    }
}
