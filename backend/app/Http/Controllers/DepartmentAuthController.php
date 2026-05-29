<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Tenant;

class DepartmentAuthController extends Controller
{
    /**
     * Handle HOD/Department web login.
     * Only users with is_hod = true can log in via web.
     * All other staff must use the mobile app.
     */
    public function login(Request $request)
    {
        $request->validate([
            'hospital_id' => 'required|string',
            'email'       => 'required|email',
            'password'    => 'required|string',
        ]);

        // Resolve tenant
        $tenant = Tenant::where('subdomain', $request->hospital_id)
            ->orWhere('id', $request->hospital_id)
            ->first();

        if (!$tenant || $tenant->status !== 'active') {
            return back()->withErrors(['hospital_id' => 'Hospital not found or inactive.']);
        }

        // Bind tenant scope
        app()->instance('current_tenant_id', $tenant->id);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        if ($user->locked_until && now()->lt($user->locked_until)) {
            return back()->withErrors(['email' => 'Account is locked. Please try again later.']);
        }

        if (!Hash::check($request->password, $user->password)) {
            $user->increment('failed_login_attempts');
            if ($user->failed_login_attempts >= 5) {
                $user->update(['locked_until' => now()->addMinutes(10)]);
                return back()->withErrors(['email' => 'Account locked due to multiple failed login attempts.']);
            }
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        if (!$user->is_active) {
            return back()->withErrors(['email' => 'Account is deactivated.']);
        }

        // Only HODs can log in via web
        if (!$user->is_hod) {
            return back()->withErrors([
                'email' => 'Web access is for Heads of Department only. Please use the mobile app.',
            ]);
        }

        if (!$user->department_id) {
            return back()->withErrors(['email' => 'You are not assigned to any department.']);
        }

        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        Auth::guard('web')->login($user);
        session(['tenant_id' => $tenant->id]);

        return redirect()->route('department.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('department.login');
    }
}
