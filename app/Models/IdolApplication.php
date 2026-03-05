<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdolApplication extends Model
{
    protected $fillable = ['user_id', 'face_photo_path', 'status', 'rejection_reason', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
