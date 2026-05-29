<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceModeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Checks the `maintenance_mode` system setting. If enabled, returns HTTP 503
     * for all API routes except Super Admin routes and the health check endpoint.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Exempt Super Admin API routes and the health check
        if ($request->is('api/v1/admin/*') || $request->is('up')) {
            return $next($request);
        }

        $maintenanceMode = SystemSetting::get('maintenance_mode', '0');

        if ($maintenanceMode === '1' || $maintenanceMode === 'true' || $maintenanceMode === true) {
            $message = SystemSetting::get(
                'maintenance_message',
                'The system is currently undergoing scheduled maintenance. Please try again later.'
            );

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'MAINTENANCE_MODE',
                    'message' => $message,
                ],
            ], 503);
        }

        return $next($request);
    }
}
