<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Tenant;
use App\Models\AuditLog;

class HospitalAdminAuthController extends Controller
{
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

        // Bind tenant scope so User queries are scoped to this tenant
        app()->instance('current_tenant_id', $tenant->id);

        // Find user within this tenant
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

        if ($user->role !== 'hospital_admin') {
            return back()->withErrors(['email' => 'Access denied. Hospital Admin role required.']);
        }

        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        // Log in via web guard
        Auth::guard('web')->login($user);

        // Store tenant in session for subsequent requests
        session(['tenant_id' => $tenant->id]);

        return redirect()->route('hospital-admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }
}
