<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class HelpCategory extends Model
{
    use HasTranslations;

    public array $translatable = ['title'];

    protected $fillable = ['title', 'sort_order'];

    public function articles(): HasMany
    {
        return $this->hasMany(HelpArticle::class, 'category_id')->orderBy('sort_order');
    }
}
