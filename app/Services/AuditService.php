<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(
        string $event,
        Model|string|null $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $workspaceId = null,
        ?string $userId = null,
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;
        $auditableType = $auditable instanceof Model ? $auditable::class : null;
        $auditableId = $auditable instanceof Model ? (string) $auditable->getKey() : $auditable;

        return AuditLog::create([
            'workspace_id' => $workspaceId ?? (app()->bound('current.workspace') ? app('current.workspace')->workspace_id : null),
            'user_id' => $userId ?? auth()->id(),
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
            'created_at' => now(),
        ]);
    }
}
