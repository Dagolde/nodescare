<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditLog;
use App\Models\RolePermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RBACMiddleware
{
    /**
     * Role-based permissions mapping.
     * Maps roles to allowed route patterns.
     */
    protected array $permissions = [
        'super_admin' => ['*'], // Super admin has access to everything
        'hospital_admin' => [
            'api/v1/departments*',
            'api/v1/staff*',
            'api/v1/workflows*',
            'api/v1/reports*',
            'api/v1/audit-logs*',
            'api/v1/patients*',
            'api/v1/patient-records*',
            'api/v1/invoices*',
            'api/v1/my-department*',
        ],
        'doctor' => [
            'api/v1/department/queue',
            'api/v1/departments',
            'api/v1/departments/*',
            'api/v1/patients*',
            'api/v1/patient-records*',
            'api/v1/appointments*',
            'api/v1/visit-types',
            'api/v1/visit-types/*',
        ],
        'front_desk_officer' => [
            'api/v1/patients',
            'api/v1/patients/search',
            'api/v1/patients/registered-today',
            'api/v1/patients/*',
            'api/v1/patients/*/documents',
            'api/v1/patients/*/new-visit',
            'api/v1/patients/*/journey',
            'api/v1/patients/*/photo/url',
            'api/v1/upload/presigned-url',
            'api/v1/department/queue',
            'api/v1/appointments/today',
            'api/v1/appointments/all',
            'api/v1/workflows/templates',
            'api/v1/visit-types',
            'api/v1/visit-types/*',
            'api/v1/doctors/by-visit-type/*',
        ],
        'accountant' => [
            'api/v1/accounts/queue',
            'api/v1/accounts*',
            'api/v1/patients/search',
            'api/v1/patients/*',
            'api/v1/patient-records*',
            'api/v1/invoices*',
            'api/v1/reports/financial',
            'api/v1/reports*',
        ],
        'pharmacist' => [
            'api/v1/department/queue',
            'api/v1/departments',
            'api/v1/patient-records*',
            'api/v1/patients*',
        ],
        'lab_technician' => [
            'api/v1/department/queue',
            'api/v1/departments',
            'api/v1/patient-records*',
            'api/v1/patients*',
        ],
        'department_staff' => [
            'api/v1/department/queue',
            'api/v1/departments',
            'api/v1/patient-records*',
            'api/v1/patients*',
            'api/v1/ward*',
        ],
        'nurse' => [
            'api/v1/department/queue',
            'api/v1/departments',
            'api/v1/patient-records*',
            'api/v1/patients*',
            'api/v1/appointments/all',
            'api/v1/ward*',
            'api/v1/visit-types',
            'api/v1/visit-types/*',
        ],
        'triage' => [
            'api/v1/department/queue',
            'api/v1/departments',
            'api/v1/patient-records*',
            'api/v1/patients*',
            'api/v1/appointments/all',
            'api/v1/visit-types',
            'api/v1/visit-types/*',
        ],
        'ward_staff' => [
            'api/v1/ward*',
            'api/v1/patients*',
            'api/v1/patient-records*',
        ],
        'ward_nurse' => [
            'api/v1/ward*',
            'api/v1/patients*',
            'api/v1/patient-records*',
        ],
    ];

    /**
     * Handle an incoming request.
     *
     * When $requiredPermission is provided (e.g. 'patients.view'), the middleware
     * checks the role_permissions table (Redis-cached) instead of URL patterns.
     * When null, falls back to the existing URL-pattern matching so no existing
     * routes break before they are explicitly migrated.
     */
    public function handle(Request $request, Closure $next, ?string $requiredPermission = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->forbiddenResponse($request, 'User not authenticated.');
        }

        $userRole = $user->role;

        // --- Named-permission gate (new behaviour) ---
        if ($requiredPermission !== null) {
            // super_admin bypasses all permission checks
            if ($userRole === 'super_admin') {
                return $next($request);
            }

            if (!$this->roleHasPermission($userRole, $requiredPermission)) {
                $this->logUnauthorizedAccess($request, $user, "Missing permission: {$requiredPermission}", 'permission');
                return $this->forbiddenResponse($request, 'Insufficient permissions.');
            }

            return $next($request);
        }

        // --- Legacy URL-pattern gate (backward-compatible fallback) ---
        $requestPath = $request->path();

        if (!$this->hasPermission($userRole, $requestPath)) {
            // HOD virtual role: grant access to my-department/* if user has is_hod flag
            if ($user->is_hod && $this->matchesPattern($requestPath, 'api/v1/my-department*')) {
                return $next($request);
            }

            $this->logUnauthorizedAccess($request, $user, "No permission for path: {$requestPath}", 'route');
            return $this->forbiddenResponse($request, 'Insufficient permissions.');
        }

        return $next($request);
    }

    /**
     * Check whether a role holds a named permission.
     * Results are cached in Redis for 3600 seconds per role.
     * Falls back to a direct DB query if Redis is unavailable.
     */
    protected function roleHasPermission(string $role, string $permission): bool
    {
        try {
            $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : 'global';
            $cacheKey = "tenant:{$tenantId}:role_perms:{$role}";

            $permissions = Cache::remember($cacheKey, 3600, function () use ($role) {
                return RolePermission::where('role', $role)->pluck('permission')->toArray();
            });
        } catch (\Exception) {
            // Redis unavailable — fall back to direct DB query (fail-closed: deny if DB also fails)
            try {
                $permissions = RolePermission::where('role', $role)->pluck('permission')->toArray();
            } catch (\Exception) {
                return false;
            }
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * Check if a role has permission for a given path.
     */
    protected function hasPermission(string $role, string $path): bool
    {
        if (!isset($this->permissions[$role])) {
            return false;
        }

        $allowedPatterns = $this->permissions[$role];

        // Super admin wildcard
        if (in_array('*', $allowedPatterns)) {
            return true;
        }

        // Check each pattern
        foreach ($allowedPatterns as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path matches a pattern (supports wildcards).
     */
    protected function matchesPattern(string $path, string $pattern): bool
    {
        // Split on * first, then quote each segment, then rejoin with .*
        $parts = explode('*', $pattern);
        $regex = implode('.*', array_map(fn($p) => preg_quote($p, '/'), $parts));
        return (bool) preg_match('/^' . $regex . '$/', $path);
    }

    /**
     * Log unauthorized access attempt to audit log.
     */
    protected function logUnauthorizedAccess(Request $request, $user, string $reason, string $resourceType = 'route'): void
    {
        try {
            AuditLog::create([
                'actor_id'      => $user->id,
                'actor_role'    => $user->role,
                'action'        => 'access.denied',
                'resource_type' => $resourceType,
                'resource_id'   => null,
                'description'   => "Unauthorized access attempt to {$request->method()} {$request->path()}",
                'ip_address'    => $request->ip(),
                'metadata'      => [
                    'reason'     => $reason,
                    'method'     => $request->method(),
                    'path'       => $request->path(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to write audit log', [
                'user_id' => $user->id,
                'path'    => $request->path(),
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Return forbidden response.
     */
    protected function forbiddenResponse(Request $request, string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => $message,
            ],
        ], 403);
    }
}
