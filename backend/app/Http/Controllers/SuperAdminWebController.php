<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuperAdminWebController extends Controller
{
    /**
     * Display the Super Admin dashboard with hospital list.
     */
    public function dashboard(Request $request)
    {
        $query = Tenant::with('plan');

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%");
            });
        }

        $hospitals = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'     => Tenant::count(),
            'active'    => Tenant::where('status', 'active')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
        ];

        return Inertia::render('SuperAdmin/Dashboard', [
            'hospitals' => $hospitals,
            'stats'     => $stats,
            'filters'   => [
                'search' => $request->search ?? '',
                'status' => $request->status ?? '',
            ],
        ]);
    }

    /**
     * Show the create hospital form.
     */
    public function createHospitalForm()
    {
        $plans = Plan::all();

        return Inertia::render('SuperAdmin/CreateHospital', [
            'plans' => $plans,
        ]);
    }

    /**
     * Show the audit logs viewer.
     */
    public function auditLogs(Request $request)
    {
        return Inertia::render('SuperAdmin/AuditLogs', [
            'filters' => [
                'start_date' => $request->start_date ?? '',
                'end_date'   => $request->end_date ?? '',
                'hospital'   => $request->hospital ?? '',
                'action'     => $request->action ?? '',
            ],
        ]);
    }

    /**
     * Show the system settings page.
     */
    public function settings()
    {
        $settings = [
            // Legacy
            'mobile_app_version'        => SystemSetting::get('mobile_app_version', '1.0.0'),
            'api_rate_limit'            => SystemSetting::get('api_rate_limit', '60'),
            // Mobile App
            'mobile_app_min_version'    => SystemSetting::get('mobile_app_min_version', '1.0.0'),
            'mobile_app_force_update'   => SystemSetting::get('mobile_app_force_update', '0') === '1',
            // Maintenance
            'maintenance_mode'          => SystemSetting::get('maintenance_mode', '0') === '1',
            'maintenance_message'       => SystemSetting::get('maintenance_message', ''),
            // File uploads
            'max_file_upload_size_mb'   => (int) SystemSetting::get('max_file_upload_size_mb', '10'),
            // Security
            'session_timeout_minutes'   => (int) SystemSetting::get('session_timeout_minutes', '60'),
            'max_failed_login_attempts' => (int) SystemSetting::get('max_failed_login_attempts', '5'),
            'lockout_duration_minutes'  => (int) SystemSetting::get('lockout_duration_minutes', '10'),
            // Workflow
            'default_workflow_template' => SystemSetting::get('default_workflow_template', ''),
            // Contact / branding
            'support_email'             => SystemSetting::get('support_email', ''),
            'support_phone'             => SystemSetting::get('support_phone', ''),
            'system_name'               => SystemSetting::get('system_name', 'HHMS'),
        ];

        $featureFlags = [
            'feature_sms_notifications' => SystemSetting::get('feature_sms_notifications', 'false') === 'true' || SystemSetting::get('feature_sms_notifications', '0') === '1',
            'feature_lab_module'        => SystemSetting::get('feature_lab_module', 'true') === 'true' || SystemSetting::get('feature_lab_module', '1') === '1',
            'feature_pharmacy_module'   => SystemSetting::get('feature_pharmacy_module', 'true') === 'true' || SystemSetting::get('feature_pharmacy_module', '1') === '1',
            'feature_appointments'      => SystemSetting::get('feature_appointments', 'true') === 'true' || SystemSetting::get('feature_appointments', '1') === '1',
            'feature_mobile_app'        => SystemSetting::get('feature_mobile_app', 'true') === 'true' || SystemSetting::get('feature_mobile_app', '1') === '1',
        ];

        return Inertia::render('SuperAdmin/Settings', [
            'settings'     => $settings,
            'featureFlags' => $featureFlags,
        ]);
    }

    /**
     * Show the system health page.
     */
    public function systemHealth()
    {
        return Inertia::render('SuperAdmin/SystemHealth');
    }

    /**
     * Show the plans management page.
     */
    public function plans()
    {
        $plans = Plan::withCount('tenants')->orderBy('name')->get();

        return Inertia::render('SuperAdmin/Plans', [
            'plans' => $plans,
        ]);
    }

    /**
     * Show the wallets management page.
     */
    public function wallets()
    {
        $plans = Plan::orderBy('name')->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Wallets', [
            'plans' => $plans,
        ]);
    }

    /**
     * Show the hospital detail page.
     */
    public function hospitalDetail(string $id)
    {
        $tenant = Tenant::with('plan')->find($id);

        if (!$tenant) {
            abort(404);
        }

        $plans = Plan::all();

        return Inertia::render('SuperAdmin/HospitalDetail', [
            'hospital' => $tenant,
            'plans'    => $plans,
        ]);
    }
}
