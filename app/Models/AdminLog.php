<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['admin_id', 'action', 'target_type', 'target_id', 'details', 'created_at'];

    protected function casts(): array
    {
        return [
            'details'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
