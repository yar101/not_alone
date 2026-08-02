<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class BanReason extends Model
{
    use HasTranslations;

    public array $translatable = ['label'];

    protected $fillable = ['label', 'type', 'sort_order'];

    public function scopeForChatBlock($query)
    {
        return $query->where('type', 'chat_block')->orderBy('sort_order');
    }

    public function scopeForUserBan($query)
    {
        return $query->where('type', 'user_ban')->orderBy('sort_order');
    }

    protected static function booted()
    {
        $clearCache = function () {
            \Illuminate\Support\Facades\Cache::forget('chat_block_reasons_list');
            \Illuminate\Support\Facades\Cache::forget('user_ban_reasons_list');
        };
        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
