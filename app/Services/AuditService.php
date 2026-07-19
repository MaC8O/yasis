<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * @param  array<string, mixed>|null  $details  Structured before/after context (e.g. per-student grade from→to).
     */
    public function log(User $user, string $action, string $entityType, ?int $entityId = null, ?array $details = null): AuditLog
    {
        $request = request();

        // Capture request context for non-repudiation, but only when the action originates
        // from an actual HTTP request. A bound route is the reliable signal for that: routed
        // controller actions have one; console commands and queue jobs (e.g. scheduled
        // retention, the create-admin command) do not, and get null IP/agent.
        $fromHttp = $request->route() !== null;
        $ipAddress = $fromHttp ? $request->ip() : null;
        $userAgent = $fromHttp && $request->userAgent() ? Str::limit($request->userAgent(), 500, '') : null;

        return DB::transaction(function () use ($user, $action, $entityType, $entityId, $details, $ipAddress, $userAgent) {
            // Lock the current chain head so concurrent appends serialize and cannot fork the
            // hash chain. The lock is held until the enclosing transaction commits, so it also
            // covers callers that wrap log() in their own DB::transaction().
            $prevHash = AuditLog::orderByDesc('id')->lockForUpdate()->value('hash');

            $log = new AuditLog([
                'user_id' => $user->id,
                'role' => $user->getRoleNames()->first() ?? 'Unknown',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details,
                'created_at' => now(),
                'prev_hash' => $prevHash,
            ]);
            $log->hash = $log->computeHash($prevHash);
            $log->save();

            return $log;
        });
    }
}
