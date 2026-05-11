<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class AdminBroadcast extends Model
{
    use HasTranslations;

    protected $fillable = ['admin_id', 'title', 'body', 'target', 'target_user_id', 'target_filters'];

    public array $translatable = ['title', 'body'];

    protected function casts(): array
    {
        return [
            'target_filters' => 'array',
        ];
    }

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
        return $query
            ->where('target', 'all')
            ->orWhere(fn($q) => $q->where('target', 'user')->where('target_user_id', $user->id))
            ->orWhere(fn($q) => $this->scopeFiltered($q, $user));
    }

    private function scopeFiltered($q, $user): void
    {
        $q->where('target', 'filtered');

        // is_idol
        $idolVal = $user->is_idol ? '1' : '0';
        $q->where(fn($s) => $s
            ->whereRaw("target_filters->>'is_idol' IS NULL")
            ->orWhereRaw("target_filters->>'is_idol' = ?", [$idolVal])
        );

        // gender
        if ($user->gender) {
            $q->where(fn($s) => $s
                ->whereRaw("target_filters->>'gender' IS NULL")
                ->orWhereRaw("target_filters->>'gender' = ?", [$user->gender])
            );
        } else {
            $q->whereRaw("target_filters->>'gender' IS NULL");
        }

        // age
        $age = $user->age;
        if ($age !== null) {
            $q->where(fn($s) => $s
                ->whereRaw("target_filters->>'age_from' IS NULL")
                ->orWhereRaw("(target_filters->>'age_from')::int <= ?", [$age])
            );
            $q->where(fn($s) => $s
                ->whereRaw("target_filters->>'age_to' IS NULL")
                ->orWhereRaw("(target_filters->>'age_to')::int >= ?", [$age])
            );
        } else {
            $q->whereRaw("target_filters->>'age_from' IS NULL");
            $q->whereRaw("target_filters->>'age_to' IS NULL");
        }

        // registered
        $createdDate = $user->created_at->toDateString();
        $q->where(fn($s) => $s
            ->whereRaw("target_filters->>'registered_from' IS NULL")
            ->orWhereRaw("(target_filters->>'registered_from')::date <= ?", [$createdDate])
        );
        $q->where(fn($s) => $s
            ->whereRaw("target_filters->>'registered_to' IS NULL")
            ->orWhereRaw("(target_filters->>'registered_to')::date >= ?", [$createdDate])
        );
    }
}
