<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * Record an immutable audit log entry.
     *
     * Failures are silently caught so audit logging never breaks the main operation.
     *
     * @param string      $action       e.g. 'patient_record.created', 'auth.login_success'
     * @param string      $resourceType e.g. 'PatientRecord', 'User', 'Invoice'
     * @param string|null $resourceId   UUID of the affected resource
     * @param string      $description  Human-readable description of the action
     * @param array       $metadata     Optional structured metadata
     * @param Request|null $request     If provided, IP address is extracted automatically
     */
    public static function record(
        string $action,
        string $resourceType,
        ?string $resourceId,
        string $description,
        array $metadata = [],
        ?Request $request = null
    ): void {
        try {
            $user = auth()->user();

            AuditLog::create([
                'actor_id'      => $user?->id,
                'actor_role'    => $user?->role,
                'action'        => $action,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'description'   => $description,
                'ip_address'    => $request?->ip(),
                'metadata'      => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            // Audit log failure must never break the main operation
            \Log::error('AuditLogService::record failed', [
                'action'        => $action,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
