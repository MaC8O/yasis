<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
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

        return AuditLog::create([
            'user_id' => $user->id,
            'role' => $user->getRoleNames()->first() ?? 'Unknown',
            'ip_address' => $fromHttp ? $request->ip() : null,
            'user_agent' => $fromHttp && $request->userAgent() ? Str::limit($request->userAgent(), 500, '') : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
