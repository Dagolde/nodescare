<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class TenantResolutionMiddleware
{
    /**
     * Resolve the current tenant and bind its ID into the app container.
     * No database switching — just sets app('current_tenant_id') for TenantScope.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        \Log::info('Tenant resolution attempt', [
            'resolved_tenant_id' => $tenant?->id,
            'resolved_tenant_name' => $tenant?->name,
            'x_tenant_id_header' => $request->header('X-Tenant-ID'),
            'hospital_id_input' => $request->input('hospital_id'),
            'session_tenant_id' => session('tenant_id'),
        ]);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TENANT_NOT_FOUND', 'message' => 'Hospital not found.'],
            ], 404);
        }

        if ($tenant->status !== 'active') {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TENANT_NOT_ACTIVE', 'message' => 'Hospital is not active.'],
            ], 403);
        }

        // Initialize tenancy — always override to ensure correct tenant
        app()->instance('current_tenant_id', $tenant->id);
        app()->instance('current_tenant', $tenant);

        // Also store on request for easy access in controllers
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        // 1. X-Tenant-ID header (mobile app)
        $tenantId = $request->header('X-Tenant-ID');
        if ($tenantId) {
            return Tenant::where('id', $tenantId)
                ->orWhere('subdomain', $tenantId)
                ->first();
        }

        // 2. hospital_id from request body (login form)
        $hospitalId = $request->input('hospital_id');
        if ($hospitalId) {
            return Tenant::where('subdomain', $hospitalId)
                ->orWhere('id', $hospitalId)
                ->first();
        }

        // 3. Session (authenticated web requests)
        $sessionTenantId = session('tenant_id');
        if ($sessionTenantId) {
            return Tenant::find($sessionTenantId);
        }

        return null;
    }
}
