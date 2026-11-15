<?php

namespace App\Services;

use App\Models\AdminLog;

class AdminLogService
{
    public static function log(
        int $adminId,
        string $action,
        string $targetType,
        ?int $targetId = 0,
        ?array $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        AdminLog::create([
            'admin_id' => $adminId,
            'action' => $action,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? (request()->userAgent() ? mb_substr(request()->userAgent(), 0, 1000) : null),
            'target_type' => $targetType,
            'target_id' => $targetId ?? 0,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
