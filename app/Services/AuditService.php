<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    public function log(User $user, string $action, string $entityType, ?int $entityId = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user->id,
            'role' => $user->getRoleNames()->first() ?? 'Unknown',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_at' => now(),
        ]);
    }
}
