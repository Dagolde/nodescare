<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DepartmentAuthMiddleware
{
    /**
     * Only HODs get web dashboard access.
     * All other staff (doctors, nurses, etc.) use the mobile app only.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('web')->check()) {
            return redirect()->route('department.login');
        }

        $user = Auth::guard('web')->user();

        // Only HODs and Hospital Admins can access department web views
        if (!$user->is_hod && $user->role !== 'hospital_admin') {
            Auth::guard('web')->logout();
            return redirect()->route('department.login')
                ->withErrors(['email' => 'Web access is for HODs only. Please use the mobile app.']);
        }

        if (!$user->is_active) {
            Auth::guard('web')->logout();
            return redirect()->route('department.login')
                ->withErrors(['email' => 'Account is deactivated.']);
        }

        // Bind user to request resolver (default guard is 'api', so auth()->user() needs this)
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
