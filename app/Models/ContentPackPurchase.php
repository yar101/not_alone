<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPackPurchase extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'content_pack_id',
        'user_id',
        'price_paid',
        'purchased_at',
        'viewed_at',
    ];

    protected $casts = [
        'price_paid' => 'integer',
        'purchased_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    public function contentPack(): BelongsTo
    {
        return $this->belongsTo(ContentPack::class)->with('user');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
