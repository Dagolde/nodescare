<?php

namespace App\Http\Controllers;

use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SuperAdminAuthController extends Controller
{
    /**
     * Handle Super Admin login for web dashboard.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $superAdmin = SuperAdmin::where('email', $request->email)->first();

        if (!$superAdmin || !Hash::check($request->password, $superAdmin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Store super admin ID in session
        session(['super_admin_id' => $superAdmin->id]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
        ]);
    }

    /**
     * Handle Super Admin logout.
     */
    public function logout(Request $request)
    {
        session()->forget('super_admin_id');
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
