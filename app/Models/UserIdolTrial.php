<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdolTrial extends Model
{
    protected $fillable = ['user_id', 'idol_id', 'order_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function idol(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idol_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
