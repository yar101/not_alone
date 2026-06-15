<?php

namespace App\Services;

use App\Models\AdminLog;

class AdminLogService
{
    public static function log(int $adminId, string $action, string $targetType, ?int $targetId = 0, ?array $details = null): void
    {
        AdminLog::create([
            'admin_id'    => $adminId,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId ?? 0,
            'details'     => $details,
            'created_at'  => now(),
        ]);
    }
}
