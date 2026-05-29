<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class TenantVerificationMiddleware
{
    /**
     * Cross-check the JWT tenant_id claim against the resolved tenant.
     *
     * This middleware must run AFTER JWTAuthMiddleware has authenticated the
     * user and bound app('current_tenant_id'). It prevents a malicious actor
     * from supplying a valid JWT for tenant A while sending X-Tenant-ID for
     * tenant B.
     *
     * Unauthenticated routes (login, refresh, password reset) do NOT use this
     * middleware — they sit in the 'tenant.resolve'-only group.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for session-authenticated web users (Hospital Admin web panel).
        // Those users are already scoped by the session tenant_id set during login.
        if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
            return $next($request);
        }

        try {
            $payload  = JWTAuth::parseToken()->getPayload();
            $jwtTenantId = $payload->get('tenant_id');
        } catch (JWTException) {
            // JWTAuthMiddleware already rejected invalid/missing tokens before
            // this middleware runs. If we somehow reach here without a token,
            // treat it as unauthorised.
            return $this->errorResponse(401, 'UNAUTHORIZED', 'Authentication required.');
        }

        if (empty($jwtTenantId)) {
            return $this->errorResponse(401, 'MISSING_TENANT_CLAIM', 'JWT is missing the tenant_id claim.');
        }

        $resolvedTenantId = app('current_tenant_id');

        if ($jwtTenantId !== $resolvedTenantId) {
            return $this->errorResponse(403, 'TENANT_MISMATCH', 'Tenant identity mismatch.');
        }

        return $next($request);
    }

    private function errorResponse(int $status, string $code, string $message): Response
    {
        return response()->json([
            'success' => false,
            'error'   => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
