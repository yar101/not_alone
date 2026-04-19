<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Interest extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = ['category_id', 'name', 'sort_order'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(InterestCategory::class, 'category_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_interests', 'interest_id', 'user_id');
    }
}
