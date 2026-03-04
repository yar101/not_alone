<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminBroadcast extends Model
{
    protected $fillable = ['admin_id', 'title', 'body', 'target', 'target_user_id'];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function reads()
    {
        return $this->hasMany(AdminBroadcastRead::class, 'broadcast_id');
    }

    public function scopeForUser($query, $user)
    {
        return $query->where('target', 'all')
            ->orWhere(fn($q) => $q->where('target', 'user')->where('target_user_id', $user->id));
    }
}
