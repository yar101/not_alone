<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminBroadcastRead extends Model
{
    protected $fillable = ['broadcast_id', 'user_id', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function broadcast()
    {
        return $this->belongsTo(AdminBroadcast::class, 'broadcast_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
