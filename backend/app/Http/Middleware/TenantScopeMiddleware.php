<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantScopeMiddleware
{
    /**
     * Ensure tenant_id is set from the authenticated JWT user if not already set.
     * This is a safety net — TenantResolutionMiddleware should have already set it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If tenant_id not yet bound (e.g. JWT auth path), get it from the authenticated user
        if (!app()->bound('current_tenant_id')) {
            $user = $request->user();
            if ($user && $user->tenant_id) {
                app()->instance('current_tenant_id', $user->tenant_id);
            }
        }

        return $next($request);
    }
}
