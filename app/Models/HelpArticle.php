<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class HelpArticle extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'content'];

    protected $fillable = ['category_id', 'title', 'content', 'sort_order'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'category_id');
    }
}
