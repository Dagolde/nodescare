<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HospitalAdminAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('web')->check()) {
            return redirect()->route('hospital-admin.login');
        }

        $user = Auth::guard('web')->user();

        // Verify user is a Hospital Admin
        if ($user->role !== 'hospital_admin') {
            Auth::guard('web')->logout();
            return redirect()->route('hospital-admin.login')
                ->withErrors(['email' => 'Access denied. Hospital Admin role required.']);
        }

        // Verify user is active
        if (!$user->is_active) {
            Auth::guard('web')->logout();
            return redirect()->route('hospital-admin.login')
                ->withErrors(['email' => 'Account is deactivated.']);
        }

        // Bind the authenticated user to the request so auth()->user() works
        // regardless of the default guard (which is 'api' for JWT).
        $request->setUserResolver(fn () => $user);

        // Ensure tenant scope is set from session (in case tenant.resolve didn't run or ran before session was available)
        if (!app()->bound('current_tenant_id') || !app('current_tenant_id')) {
            $sessionTenantId = session('tenant_id');
            if ($sessionTenantId) {
                app()->instance('current_tenant_id', $sessionTenantId);
            } elseif ($user->tenant_id) {
                app()->instance('current_tenant_id', $user->tenant_id);
            }
        }

        return $next($request);
    }
}
