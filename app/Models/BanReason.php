<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BanReason extends Model
{
    protected $fillable = ['label', 'type', 'sort_order'];

    public function scopeForChatBlock($query)
    {
        return $query->where('type', 'chat_block')->orderBy('sort_order');
    }

    public function scopeForUserBan($query)
    {
        return $query->where('type', 'user_ban')->orderBy('sort_order');
    }
}
