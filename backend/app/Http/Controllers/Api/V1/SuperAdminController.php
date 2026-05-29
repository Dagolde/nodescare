<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTenantRequest;
use App\Http\Requests\Admin\UpdateFeatureFlagRequest;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Models\Department;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    // -------------------------------------------------------------------------
    // Hospitals
    // -------------------------------------------------------------------------

    public function listHospitals(Request $request): JsonResponse
    {
        $query = Tenant::with('plan');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%");
            });
        }

        $tenants = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $tenants->items(),
            'meta'    => [
                'pagination' => [
                    'current_page' => $tenants->currentPage(),
                    'per_page'     => $tenants->perPage(),
                    'total'        => $tenants->total(),
                    'last_page'    => $tenants->lastPage(),
                ],
            ],
        ]);
    }

    public function getHospital(string $id): JsonResponse
    {
        $tenant = Tenant::with('plan')->find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TENANT_NOT_FOUND', 'message' => 'Hospital not found.'],
            ], 404);
        }

        // Get stats directly from the single DB using tenant_id
        $stats = [
            'staff_count'           => User::withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'department_count'      => Department::withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'patient_count'         => \App\Models\Patient::withoutGlobalScopes()->where('tenant_id', $id)->count(),
            'active_patients_today' => \App\Models\Patient::withoutGlobalScopes()
                ->where('tenant_id', $id)
                ->whereDate('created_at', today())
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => array_merge($tenant->toArray(), ['stats' => $stats]),
        ]);
    }

    public function updateHospital(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'name'      => ['nullable', 'string', 'max:255'],
            'subdomain' => ['nullable', 'string', 'max:100', 'unique:tenants,subdomain,' . $id],
            'plan_id'   => ['nullable', 'string', 'exists:plans,id'],
        ]);

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TENANT_NOT_FOUND', 'message' => 'Hospital not found.'],
            ], 404);
        }

        $tenant->update(array_filter($request->only(['name', 'subdomain', 'plan_id'])));

        return response()->json([
            'success' => true,
            'data'    => $tenant->fresh('plan'),
            'message' => 'Hospital updated successfully.',
        ]);
    }

    /**
     * Create hospital + admin user in one transaction.
     * No separate DB needed — everything goes into the single `cares` database.
     */
    public function createHospital(CreateTenantRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tenant = Tenant::create([
                'id'        => Str::uuid(),
                'name'      => $request->input('name'),
                'subdomain' => $request->input('subdomain'),
                'status'    => 'active',
                'plan_id'   => $request->input('plan_id'),
            ]);

            // Create default departments
            $departments = $this->createDefaultDepartments($tenant->id);

            // Create default workflow template
            $this->createDefaultWorkflow($tenant->id, $departments);

            // Create Hospital Admin user
            $password = $request->input('admin_password') ?? Str::random(12);

            User::withoutGlobalScopes()->create([
                'id'                    => Str::uuid(),
                'tenant_id'             => $tenant->id,
                'name'                  => $request->input('admin_name'),
                'email'                 => $request->input('admin_email'),
                'password'              => Hash::make($password),
                'role'                  => 'hospital_admin',
                'is_active'             => true,
                'token_version'         => 0,
                'failed_login_attempts' => 0,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $tenant->fresh('plan'),
                'message' => 'Hospital created successfully.',
                'credentials' => [
                    'email'    => $request->input('admin_email'),
                    'password' => $password,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to create hospital', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => ['code' => 'CREATION_FAILED', 'message' => 'Failed to create hospital: ' . $e->getMessage()],
            ], 500);
        }
    }

    private function createDefaultDepartments(string $tenantId): array
    {
        $defaults = [
            'accounts'   => ['name' => 'Accounts Department',  'code' => 'ACCTS',  'type' => 'accounts'],
            'front_desk' => ['name' => 'Front Desk',           'code' => 'FRONT',  'type' => 'front_desk'],
            'triage'     => ['name' => 'Triage/Nursing',       'code' => 'TRIAGE', 'type' => 'triage'],
            'doctor'     => ['name' => 'Doctor Consultation',  'code' => 'DOC',    'type' => 'clinical'],
            'pharmacy'   => ['name' => 'Pharmacy',             'code' => 'PHARM',  'type' => 'pharmacy'],
            'laboratory' => ['name' => 'Laboratory',           'code' => 'LAB',    'type' => 'laboratory'],
        ];

        $departments = [];
        foreach ($defaults as $key => $data) {
            $departments[$key] = Department::withoutGlobalScopes()->create(
                array_merge($data, ['id' => Str::uuid(), 'tenant_id' => $tenantId])
            );
        }

        return $departments;
    }

    private function createDefaultWorkflow(string $tenantId, array $departments): void
    {
        $template = WorkflowTemplate::withoutGlobalScopes()->create([
            'id'          => Str::uuid(),
            'tenant_id'   => $tenantId,
            'name'        => 'Standard Patient Journey',
            'description' => 'Default workflow: Front Desk → Triage → Doctor → Pharmacy',
            'is_default'  => true,
            'created_by'  => null,
        ]);

        $steps = [
            ['department' => 'front_desk', 'step_order' => 1, 'is_terminal' => false],
            ['department' => 'triage',     'step_order' => 2, 'is_terminal' => false],
            ['department' => 'doctor',     'step_order' => 3, 'is_terminal' => false],
            ['department' => 'pharmacy',   'step_order' => 4, 'is_terminal' => true],
        ];

        foreach ($steps as $step) {
            WorkflowTemplateStep::withoutGlobalScopes()->create([
                'id'            => Str::uuid(),
                'tenant_id'     => $tenantId,
                'template_id'   => $template->id,
                'department_id' => $departments[$step['department']]->id,
                'step_order'    => $step['step_order'],
                'is_terminal'   => $step['is_terminal'],
            ]);
        }
    }

    public function suspendHospital(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TENANT_NOT_FOUND', 'message' => 'Hospital not found.'],
            ], 404);
        }

        $tenant->update(['status' => 'suspended']);

        // Invalidate all user tokens for this tenant
        User::withoutGlobalScopes()
            ->where('tenant_id', $id)
            ->increment('token_version');

        return response()->json([
            'success' => true,
            'data'    => $tenant->fresh(),
            'message' => 'Hospital suspended. All sessions invalidated.',
        ]);
    }

    public function activateHospital(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'TENANT_NOT_FOUND', 'message' => 'Hospital not found.'],
            ], 404);
        }

        $tenant->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'data'    => $tenant->fresh(),
            'message' => 'Hospital activated.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    public function getSettings(): JsonResponse
    {
        $settings = Cache::remember('system_settings', 60, function () {
            return SystemSetting::all()->mapWithKeys(fn ($s) => [$s->key => $s->value])->toArray();
        });

        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function updateSettings(UpdateSystemSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated() as $key => $value) {
            if ($value !== null) {
                SystemSetting::set($key, $value, auth()->id());
            }
        }

        Cache::forget('system_settings');
        Cache::forget('feature_flags');

        return response()->json(['success' => true, 'message' => 'Settings updated.']);
    }

    public function getFeatureFlags(): JsonResponse
    {
        $flags = Cache::remember('feature_flags', 60, function () {
            return SystemSetting::whereIn('key', [
                'feature_sms_notifications', 'feature_lab_module',
                'feature_pharmacy_module', 'feature_appointments', 'feature_mobile_app',
            ])->get()->mapWithKeys(fn ($s) => [$s->key => in_array($s->value, ['true', '1'])]);
        });

        return response()->json(['success' => true, 'data' => $flags]);
    }

    public function updateFeatureFlag(string $key, UpdateFeatureFlagRequest $request): JsonResponse
    {
        SystemSetting::set($key, $request->input('enabled') ? '1' : '0', auth()->id());
        Cache::forget('feature_flags');
        Cache::forget('system_settings');

        return response()->json([
            'success' => true,
            'message' => 'Feature flag updated.',
            'data'    => ['key' => $key, 'enabled' => $request->input('enabled')],
        ]);
    }

    // -------------------------------------------------------------------------
    // Plans
    // -------------------------------------------------------------------------

    public function listPlans(): JsonResponse
    {
        $plans = Plan::withCount('tenants')->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function createPlan(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => ['required', 'string', 'max:255', 'unique:plans,name'],
            'api_rate_limit' => ['nullable', 'integer', 'min:1'],
            'feature_flags'  => ['nullable', 'array'],
        ]);

        try {
            $plan = Plan::create([
                'name'           => $request->input('name'),
                'api_rate_limit' => $request->input('api_rate_limit', 60),
                'feature_flags'  => $request->input('feature_flags', []),
            ]);

            return response()->json(['success' => true, 'data' => $plan, 'message' => 'Plan created.'], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create plan', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'CREATION_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    public function updatePlan(string $id, Request $request): JsonResponse
    {
        $plan = Plan::find($id);
        if (!$plan) {
            return response()->json(['success' => false, 'error' => ['code' => 'PLAN_NOT_FOUND', 'message' => 'Plan not found.']], 404);
        }

        $request->validate([
            'name'           => ['nullable', 'string', 'max:255', 'unique:plans,name,' . $id],
            'api_rate_limit' => ['nullable', 'integer', 'min:1'],
            'feature_flags'  => ['nullable', 'array'],
        ]);

        $plan->update(array_filter($request->only(['name', 'api_rate_limit', 'feature_flags']), fn ($v) => $v !== null));

        return response()->json(['success' => true, 'data' => $plan->fresh(), 'message' => 'Plan updated.']);
    }

    public function deletePlan(string $id): JsonResponse
    {
        $plan = Plan::withCount('tenants')->find($id);
        if (!$plan) {
            return response()->json(['success' => false, 'error' => ['code' => 'PLAN_NOT_FOUND', 'message' => 'Plan not found.']], 404);
        }

        if ($plan->tenants_count > 0) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'PLAN_IN_USE', 'message' => "Cannot delete — {$plan->tenants_count} hospital(s) on this plan."],
            ], 409);
        }

        $plan->delete();
        return response()->json(['success' => true, 'message' => 'Plan deleted.']);
    }

    // -------------------------------------------------------------------------
    // System Health
    // -------------------------------------------------------------------------

    public function systemHealth(): JsonResponse
    {
        $health = ['database' => 'ok', 'storage' => 'ok'];

        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $health['database'] = 'error';
        }

        try {
            \Storage::disk('local')->put('health_check.tmp', 'ok');
            \Storage::disk('local')->delete('health_check.tmp');
        } catch (\Exception $e) {
            $health['storage'] = 'error';
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($health, [
                'total_hospitals'     => Tenant::count(),
                'active_hospitals'    => Tenant::where('status', 'active')->count(),
                'suspended_hospitals' => Tenant::where('status', 'suspended')->count(),
            ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Audit Logs
    // -------------------------------------------------------------------------

    public function getAuditLogs(Request $request): JsonResponse
    {
        $query = \App\Models\AuditLog::withoutGlobalScopes()->with('actor');

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }
        if ($request->has('action')) {
            $query->where('action', 'like', '%' . $request->input('action') . '%');
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $logs->items(),
            'meta'    => [
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page'     => $logs->perPage(),
                    'total'        => $logs->total(),
                    'last_page'    => $logs->lastPage(),
                ],
            ],
        ]);
    }

    public function exportAuditLogs(Request $request)
    {
        $query = \App\Models\AuditLog::withoutGlobalScopes()->with('actor');

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        $logs     = $query->orderBy('created_at', 'desc')->get();
        $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.csv';

        return response()->stream(function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Actor', 'Role', 'Action', 'Resource', 'Description', 'IP', 'Timestamp']);
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->actor?->name ?? 'System',
                    $log->actor_role,
                    $log->action,
                    $log->resource_type,
                    $log->description,
                    $log->ip_address,
                    $log->created_at->toDateTimeString(),
                ]);
            }
            fclose($file);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'type'    => ['nullable', 'string', 'in:info,warning,critical'],
        ]);

        $activeTenants = Tenant::where('status', 'active')->get();
        $dispatched    = 0;

        foreach ($activeTenants as $tenant) {
            \App\Models\Notification::withoutGlobalScopes()->create([
                'id'           => Str::uuid(),
                'tenant_id'    => $tenant->id,
                'recipient_id' => null,
                'type'         => 'system_broadcast',
                'channel'      => 'system',
                'payload'      => [
                    'title'   => $request->input('title'),
                    'message' => $request->input('message'),
                    'type'    => $request->input('type', 'info'),
                ],
                'status'   => 'delivered',
                'attempts' => 1,
            ]);
            $dispatched++;
        }

        return response()->json([
            'success' => true,
            'message' => "Broadcast sent to {$dispatched} hospital(s).",
        ]);
    }
}
