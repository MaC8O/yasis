<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function log(User $user, string $action, string $entityType, ?int $entityId = null): AuditLog
    {
        return DB::transaction(function () use ($user, $action, $entityType, $entityId) {
            // Lock the current chain head so concurrent appends serialize and cannot fork the
            // hash chain. The lock is held until the enclosing transaction commits, so it also
            // covers callers that wrap log() in their own DB::transaction().
            $prevHash = AuditLog::orderByDesc('id')->lockForUpdate()->value('hash');

            $log = new AuditLog([
                'user_id' => $user->id,
                'role' => $user->getRoleNames()->first() ?? 'Unknown',
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'created_at' => now(),
                'prev_hash' => $prevHash,
            ]);
            $log->hash = $log->computeHash($prevHash);
            $log->save();

            return $log;
        });
    }
}
