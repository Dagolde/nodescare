<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HospitalAdmin\CreateDepartmentRequest;
use App\Http\Requests\HospitalAdmin\UpdateDepartmentRequest;
use App\Http\Requests\HospitalAdmin\CreateStaffRequest;
use App\Http\Requests\HospitalAdmin\UpdateStaffRequest;
use App\Http\Requests\HospitalAdmin\CreateWorkflowTemplateRequest;
use App\Http\Requests\HospitalAdmin\UpdateWorkflowTemplateRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HospitalAdminController extends Controller
{
    /**
     * Get the current tenant ID reliably — from container, session, or authenticated user.
     */
    private function tenantId(): string
    {
        return app('current_tenant_id')
            ?? session('tenant_id')
            ?? $this->authUser()?->tenant_id
            ?? '';
    }

    /**
     * Get the authenticated user — works for both web session and JWT API.
     * Web routes use session guard; API routes use JWT guard.
     */
    private function authUser(): ?\App\Models\User
    {
        // request()->user() uses the resolver set by middleware (works for both web and JWT)
        $user = request()->user();
        if ($user) return $user;

        // Fallback: try web guard (Hospital Admin web dashboard)
        $webUser = auth('web')->user();
        if ($webUser) return $webUser;

        // Fallback: try api guard (mobile JWT)
        return auth('api')->user();
    }

    /**
     * List all departments.
     *
     * GET /api/v1/departments
     */
    public function listDepartments(Request $request): JsonResponse
    {
        $query = Department::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $departments = $query->orderBy('name')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $departments->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $departments->currentPage(),
                    'per_page'     => $departments->perPage(),
                    'total'        => $departments->total(),
                    'last_page'    => $departments->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Create a new department.
     *
     * POST /api/v1/departments
     */
    public function createDepartment(CreateDepartmentRequest $request): JsonResponse
    {
        try {
            $department = Department::create([
                'id'        => Str::uuid(),
                'tenant_id' => $this->tenantId(),
                'name'      => $request->input('name'),
                'code'      => $request->input('code'),
                'type'      => $request->input('type'),
                'is_active' => $request->input('is_active', true),
            ]);

            AuditLog::create([
                'tenant_id'     => $this->tenantId(),
                'actor_id'      => $this->authUser()?->id,
                'actor_role'    => $this->authUser()?->role,
                'action'        => 'department.created',
                'resource_type' => 'Department',
                'resource_id'   => $department->id,
                'description'   => "Created department: {$department->name}",
                'ip_address'    => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $department,
                'message' => 'Department created successfully.',
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create department', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => ['code' => 'CREATION_FAILED', 'message' => 'Failed to create department.'],
            ], 500);
        }
    }

    /**
     * Update a department.
     *
     * PUT /api/v1/departments/{id}
     */
    public function updateDepartment(string $id, UpdateDepartmentRequest $request): JsonResponse
    {
        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPARTMENT_NOT_FOUND',
                    'message' => 'Department not found.',
                ],
            ], 404);
        }

        try {
            $department->update($request->validated());

            AuditLog::create([
                'tenant_id'     => $this->tenantId(),
                'actor_id'      => $this->authUser()?->id,
                'actor_role'    => $this->authUser()?->role,
                'action'        => 'department.updated',
                'resource_type' => 'Department',
                'resource_id'   => $department->id,
                'description'   => "Updated department: {$department->name}",
                'ip_address'    => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $department->fresh(),
                'message' => 'Department updated successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update department', [
                'department_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => 'Failed to update department.',
                ],
            ], 500);
        }
    }

    /**
     * Delete a department (with active record guard).
     *
     * DELETE /api/v1/departments/{id}
     */
    public function deleteDepartment(string $id): JsonResponse
    {
        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPARTMENT_NOT_FOUND',
                    'message' => 'Department not found.',
                ],
            ], 404);
        }

        // Check for active patient records
        $activeRecordsCount = PatientRecord::where('current_department_id', $id)
            ->whereIn('status', ['active', 'in_transit', 'admitted'])
            ->count();

        if ($activeRecordsCount > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPARTMENT_HAS_ACTIVE_RECORDS',
                    'message' => "Cannot delete department. There are {$activeRecordsCount} active patient records in this department.",
                    'details' => [
                        'active_records_count' => $activeRecordsCount,
                    ],
                ],
            ], 400);
        }

        try {
            $departmentName = $department->name;
            $department->delete();

            AuditLog::create([
                'tenant_id'     => $this->tenantId(),
                'actor_id'      => $this->authUser()?->id,
                'actor_role'    => $this->authUser()?->role,
                'action'        => 'department.deleted',
                'resource_type' => 'Department',
                'resource_id'   => $id,
                'description'   => "Deleted department: {$departmentName}",
                'ip_address'    => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to delete department', [
                'department_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETION_FAILED',
                    'message' => 'Failed to delete department.',
                ],
            ], 500);
        }
    }

    /**
     * List all staff accounts.
     *
     * GET /api/v1/staff
     */
    public function listStaff(Request $request): JsonResponse
    {
        $query = User::with('department');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $staff = $query->orderBy('name')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $staff->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $staff->currentPage(),
                    'per_page'     => $staff->perPage(),
                    'total'        => $staff->total(),
                    'last_page'    => $staff->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Create a new staff account.
     *
     * POST /api/v1/staff
     */
    public function createStaff(CreateStaffRequest $request): JsonResponse
    {
        try {
            $staff = User::create([
                'id'            => Str::uuid(),
                'tenant_id'     => $this->tenantId(),
                'name'          => $request->input('name'),
                'email'         => $request->input('email'),
                'phone'         => $request->input('phone'),
                'password'      => Hash::make($request->input('password')),
                'role'          => $request->input('role'),
                'department_id' => $request->input('department_id'),
                'is_active'     => $request->input('is_active', true),
                'is_hod'        => $request->input('is_hod', false),
                'specialties'   => $request->input('specialties', []),
                'created_by'    => $this->authUser()?->id,
                'token_version' => 0,
            ]);

            AuditLog::create([
                'tenant_id'     => $this->tenantId(),
                'actor_id'      => $this->authUser()?->id,
                'actor_role'    => $this->authUser()?->role,
                'action'        => 'staff.created',
                'resource_type' => 'User',
                'resource_id'   => $staff->id,
                'description'   => "Created staff account: {$staff->name} ({$staff->role})",
                'ip_address'    => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $staff->load('department'),
                'message' => 'Staff account created successfully.',
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create staff account', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => ['code' => 'CREATION_FAILED', 'message' => 'Failed to create staff account.'],
            ], 500);
        }
    }

    /**
     * Update a staff account.
     *
     * PUT /api/v1/staff/{id}
     */
    public function updateStaff(string $id, UpdateStaffRequest $request): JsonResponse
    {
        $staff = User::find($id);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STAFF_NOT_FOUND',
                    'message' => 'Staff account not found.',
                ],
            ], 404);
        }

        try {
            $updateData = $request->validated();

            // Hash password if provided
            if (isset($updateData['password'])) {
                $updateData['password'] = Hash::make($updateData['password']);
            }

            // Ensure specialties is always set (even as empty array) so it gets saved
            if (!array_key_exists('specialties', $updateData)) {
                $updateData['specialties'] = [];
            }

            $staff->update($updateData);

            AuditLog::create([
                'tenant_id'     => $this->tenantId(),
                'actor_id'      => $this->authUser()?->id,
                'actor_role'    => $this->authUser()?->role,
                'action'        => 'staff.updated',
                'resource_type' => 'User',
                'resource_id'   => $staff->id,
                'description'   => "Updated staff account: {$staff->name}",
                'ip_address'    => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $staff->fresh()->load('department'),
                'message' => 'Staff account updated successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update staff account', [
                'staff_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => 'Failed to update staff account.',
                ],
            ], 500);
        }
    }

    /**
     * Deactivate a staff account and increment token_version.
     *
     * PATCH /api/v1/staff/{id}/deactivate
     */
    public function deactivateStaff(string $id): JsonResponse
    {
        $staff = User::find($id);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STAFF_NOT_FOUND',
                    'message' => 'Staff account not found.',
                ],
            ], 404);
        }

        if (!$staff->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALREADY_DEACTIVATED',
                    'message' => 'Staff account is already deactivated.',
                ],
            ], 400);
        }

        try {
            DB::beginTransaction();

            $staff->update([
                'is_active' => false,
                'token_version' => $staff->token_version + 1,
            ]);

            AuditLog::create([
                'tenant_id'     => $this->tenantId(),
                'actor_id'      => $this->authUser()?->id,
                'actor_role'    => $this->authUser()?->role,
                'action'        => 'staff.deactivated',
                'resource_type' => 'User',
                'resource_id'   => $staff->id,
                'description'   => "Deactivated staff account: {$staff->name}. All sessions invalidated.",
                'ip_address'    => request()->ip(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $staff->fresh(),
                'message' => 'Staff account deactivated successfully. All active sessions have been invalidated.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Failed to deactivate staff account', [
                'staff_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEACTIVATION_FAILED',
                    'message' => 'Failed to deactivate staff account.',
                ],
            ], 500);
        }
    }

    /**
     * List all workflow templates.
     *
     * GET /api/v1/workflows/templates
     */
    public function listWorkflowTemplates(Request $request): JsonResponse
    {
        $query = WorkflowTemplate::with(['steps.department', 'creator']);

        if ($request->has('is_default')) {
            $query->where('is_default', $request->boolean('is_default'));
        }

        $cacheKey = 'tenant:' . $this->tenantId() . ':workflow_templates';

        $templates = Cache::remember($cacheKey, 300, function () use ($query) {
            return $query->orderBy('created_at', 'desc')->get();
        });

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Create a new workflow template.
     *
     * POST /api/v1/workflows/templates
     */
    public function createWorkflowTemplate(CreateWorkflowTemplateRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tenantId = $this->tenantId();

            $template = WorkflowTemplate::create([
                'id'          => Str::uuid(),
                'tenant_id'   => $tenantId,
                'name'        => $request->input('name'),
                'description' => $request->input('description'),
                'is_default'  => $request->input('is_default', false),
                'created_by'  => $this->authUser()?->id,
            ]);

            // Create workflow steps
            if ($request->has('steps')) {
                foreach ($request->input('steps') as $stepData) {
                    WorkflowTemplateStep::create([
                        'id'            => Str::uuid(),
                        'tenant_id'     => $tenantId,
                        'template_id'   => $template->id,
                        'department_id' => $stepData['department_id'],
                        'step_order'    => $stepData['step_order'],
                        'is_terminal'   => $stepData['is_terminal'] ?? false,
                    ]);
                }
            }

            AuditLog::create([
                'tenant_id'     => $tenantId,
                'actor_id'      => $this->authUser()?->id,
                'actor_role'    => $this->authUser()?->role,
                'action'        => 'workflow_template.created',
                'resource_type' => 'WorkflowTemplate',
                'resource_id'   => $template->id,
                'description'   => "Created workflow template: {$template->name}",
                'ip_address'    => request()->ip(),
            ]);

            DB::commit();

            Cache::forget('tenant:' . $tenantId . ':workflow_templates');

            return response()->json([
                'success' => true,
                'data' => $template->load(['steps.department', 'creator']),
                'message' => 'Workflow template created successfully.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Failed to create workflow template', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'CREATION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Update a workflow template.
     *
     * PUT /api/v1/workflows/templates/{id}
     */
    public function updateWorkflowTemplate(string $id, UpdateWorkflowTemplateRequest $request): JsonResponse
    {
        $template = WorkflowTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEMPLATE_NOT_FOUND',
                    'message' => 'Workflow template not found.',
                ],
            ], 404);
        }

        try {
            DB::beginTransaction();

            $template->update([
                'name' => $request->input('name', $template->name),
                'description' => $request->input('description', $template->description),
                'is_default' => $request->input('is_default', $template->is_default),
            ]);

            // Update steps if provided
            if ($request->has('steps')) {
                // Delete existing steps (bypass scope to ensure deletion works)
                WorkflowTemplateStep::withoutGlobalScopes()
                    ->where('template_id', $template->id)
                    ->delete();

                // Create new steps
                foreach ($request->input('steps') as $stepData) {
                    WorkflowTemplateStep::create([
                        'id'            => Str::uuid(),
                        'tenant_id'     => $this->tenantId() ?: $template->tenant_id,
                        'template_id'   => $template->id,
                        'department_id' => $stepData['department_id'],
                        'step_order'    => $stepData['step_order'],
                        'is_terminal'   => $stepData['is_terminal'] ?? false,
                    ]);
                }
            }

            AuditLog::create([
                'tenant_id'     => $this->tenantId(),
                'actor_id'      => $this->authUser()?->id,
                'actor_role'    => $this->authUser()?->role,
                'action'        => 'workflow_template.updated',
                'resource_type' => 'WorkflowTemplate',
                'resource_id'   => $template->id,
                'description'   => "Updated workflow template: {$template->name}",
                'ip_address'    => request()->ip(),
            ]);

            DB::commit();

            // Invalidate workflow templates cache
            Cache::forget('tenant:' . $this->tenantId() . ':workflow_templates');

            return response()->json([
                'success' => true,
                'data' => $template->fresh()->load(['steps.department', 'creator']),
                'message' => 'Workflow template updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Failed to update workflow template', [
                'template_id' => $id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get department activity report.
     *
     * GET /api/v1/reports/department-activity
     */
    public function getDepartmentActivityReport(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'department_id' => 'nullable|uuid|exists:departments,id',
        ]);

        $query = PatientJourneyEvent::query();

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        // Filter by department
        if ($request->has('department_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('from_department_id', $request->input('department_id'))
                  ->orWhere('to_department_id', $request->input('department_id'));
            });
        }

        // Get department statistics
        $departments = Department::withCount([
            'currentPatientRecords as pending_count' => function ($q) {
                $q->whereIn('status', ['active', 'in_transit']);
            }
        ])->get();

        $departmentStats = $departments->map(function ($dept) use ($request) {
            $eventsQuery = PatientJourneyEvent::where('to_department_id', $dept->id);

            if ($request->has('start_date')) {
                $eventsQuery->where('created_at', '>=', $request->input('start_date'));
            }
            if ($request->has('end_date')) {
                $eventsQuery->where('created_at', '<=', $request->input('end_date'));
            }

            $throughput = $eventsQuery->count();

            // Calculate average wait time (time between routed and completed)
            $completedEvents = PatientJourneyEvent::where('from_department_id', $dept->id)
                ->where('event_type', 'routed');

            if ($request->has('start_date')) {
                $completedEvents->where('created_at', '>=', $request->input('start_date'));
            }
            if ($request->has('end_date')) {
                $completedEvents->where('created_at', '<=', $request->input('end_date'));
            }

            $completedEvents = $completedEvents->get();

            $totalWaitTime = 0;
            $waitTimeCount = 0;

            foreach ($completedEvents as $event) {
                $arrivalEvent = PatientJourneyEvent::where('patient_record_id', $event->patient_record_id)
                    ->where('to_department_id', $dept->id)
                    ->where('created_at', '<', $event->created_at)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($arrivalEvent) {
                    $waitTime = $event->created_at->diffInMinutes($arrivalEvent->created_at);
                    $totalWaitTime += $waitTime;
                    $waitTimeCount++;
                }
            }

            $avgWaitTime = $waitTimeCount > 0 ? round($totalWaitTime / $waitTimeCount, 2) : 0;

            return [
                'department_id' => $dept->id,
                'department_name' => $dept->name,
                'department_code' => $dept->code,
                'throughput' => $throughput,
                'pending_count' => $dept->pending_count,
                'avg_wait_time_minutes' => $avgWaitTime,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $departmentStats,
        ]);
    }

    /**
     * Get hospital-scoped audit logs.
     *
     * GET /api/v1/audit-logs
     */
    public function getAuditLogs(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'action' => 'nullable|string',
            'actor_id' => 'nullable|uuid',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AuditLog::with('actor');

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', 'like', "%{$request->input('action')}%");
        }

        // Filter by actor
        if ($request->has('actor_id')) {
            $query->where('actor_id', $request->input('actor_id'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Export audit logs as CSV.
     *
     * GET /api/v1/audit-logs/export
     */
    public function exportAuditLogs(Request $request)
    {
        $request->validate([
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'action'      => 'nullable|string',
            'user_id'     => 'nullable|uuid',
            'action_type' => 'nullable|string',
        ]);

        $query = AuditLog::with('actor');

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        // Filter by exact action substring
        if ($request->has('action')) {
            $query->where('action', 'like', "%{$request->input('action')}%");
        }

        // Filter by actor (user_id)
        if ($request->has('user_id')) {
            $query->where('actor_id', $request->input('user_id'));
        }

        // Filter by action type prefix (e.g. 'patient_record' matches 'patient_record.created')
        if ($request->has('action_type')) {
            $prefix = $request->input('action_type');
            $query->where(function ($q) use ($prefix) {
                $q->where('action', $prefix)
                  ->orWhere('action', 'like', "{$prefix}.%");
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'audit_logs_' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID',
                'Actor Name',
                'Actor Role',
                'Action',
                'Resource Type',
                'Resource ID',
                'Description',
                'IP Address',
                'Timestamp',
            ]);

            // CSV rows
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->actor ? $log->actor->name : 'System',
                    $log->actor_role,
                    $log->action,
                    $log->resource_type,
                    $log->resource_id,
                    $log->description,
                    $log->ip_address,
                    $log->created_at->toDateTimeString(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get financial report.
     *
     * GET /api/v1/reports/financial
     */
    public function getFinancialReport(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $invoiceQuery = Invoice::query();

        // Filter by date range
        if ($request->filled('start_date')) {
            $invoiceQuery->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $invoiceQuery->whereDate('created_at', '<=', $request->input('end_date'));
        }

        // Total revenue (fully paid invoices)
        $totalRevenue = (clone $invoiceQuery)
            ->where('status', 'paid')
            ->sum('paid_amount');

        // Outstanding amount (issued + partially paid)
        $outstandingInvoices = (clone $invoiceQuery)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->selectRaw('SUM(total_amount - paid_amount) as outstanding')
            ->value('outstanding') ?? 0;

        // Payment breakdown by method
        $paymentQuery = InvoicePayment::query();

        if ($request->filled('start_date')) {
            $paymentQuery->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $paymentQuery->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $paymentBreakdown = $paymentQuery
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->payment_method => (float) $item->total];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => (float) $totalRevenue,
                'outstanding_invoices' => (float) $outstandingInvoices,
                'payment_breakdown' => [
                    'cash' => $paymentBreakdown->get('cash', 0),
                    'pos_card' => $paymentBreakdown->get('card', 0) + $paymentBreakdown->get('pos_card', 0),
                    'hmo_insurance' => $paymentBreakdown->get('hmo', 0) + $paymentBreakdown->get('hmo_insurance', 0),
                    'transfer' => $paymentBreakdown->get('transfer', 0),
                    'other' => $paymentBreakdown->get('other', 0),
                ],
            ],
        ]);
    }
}
