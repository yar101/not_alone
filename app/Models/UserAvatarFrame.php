<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAvatarFrame extends Model
{
    protected $fillable = [
        'user_id',
        'avatar_frame_id',
        'acquired_at',
    ];

    protected $casts = [
        'acquired_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function frame()
    {
        return $this->belongsTo(AvatarFrame::class, 'avatar_frame_id');
    }
}
