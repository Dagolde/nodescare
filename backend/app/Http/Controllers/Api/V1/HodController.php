<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Rules\PasswordComplexity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HodController extends Controller
{
    /**
     * Allowed roles that an HOD can create/manage.
     */
    protected array $allowedRoles = [
        'doctor',
        'front_desk_officer',
        'pharmacist',
        'lab_technician',
        'department_staff',
    ];

    /**
     * Ensure the authenticated user is an HOD.
     */
    protected function ensureHod(): ?JsonResponse
    {
        $user = request()->user() ?? auth('web')->user();

        if (!$user || !$user->isHod()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Only Heads of Department can manage department staff.',
                ],
            ], 403);
        }

        return null;
    }

    /**
     * GET /api/v1/my-department/staff
     * List all staff in the HOD's own department.
     */
    public function listDepartmentStaff(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $query = User::with('department')
            ->where('department_id', $hod->department_id);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
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
     * POST /api/v1/my-department/staff
     * Create a new staff account in the HOD's department.
     */
    public function createDepartmentStaff(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'required|string|max:20',
            'password' => ['required', 'string', 'min:10', new PasswordComplexity()],
            'role'     => ['required', 'string', Rule::in($this->allowedRoles)],
        ]);

        try {
            $staff = User::create([
                'id'            => Str::uuid(),
                'tenant_id'     => app('current_tenant_id'),
                'name'          => $validated['name'],
                'email'         => $validated['email'],
                'phone'         => $validated['phone'],
                'password'      => Hash::make($validated['password']),
                'role'          => $validated['role'],
                'department_id' => $hod->department_id,
                'is_active'     => true,
                'is_hod'        => false,
                'created_by'    => $hod->id,
                'token_version' => 0,
            ]);

            AuditLog::create([
                'tenant_id'     => app('current_tenant_id'),
                'actor_id'      => $hod->id,
                'actor_role'    => $hod->role,
                'action'        => 'staff.created',
                'resource_type' => 'User',
                'resource_id'   => $staff->id,
                'description'   => "HOD created staff account: {$staff->name} ({$staff->role})",
                'ip_address'    => $request->ip(),
            ]);

            // Invalidate staff workload cache for the department
            app(\App\Services\DepartmentStatisticsService::class)->invalidateCache($hod->department_id);

            return response()->json([
                'success' => true,
                'data'    => $staff->load('department'),
                'message' => 'Staff account created successfully.',
            ], 201);
        } catch (\Exception $e) {
            \Log::error('HOD failed to create staff account', [
                'error'   => $e->getMessage(),
                'hod_id'  => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'CREATION_FAILED',
                    'message' => 'Failed to create staff account.',
                ],
            ], 500);
        }
    }

    /**
     * PUT /api/v1/my-department/staff/{id}
     * Update a staff account in the HOD's department.
     */
    public function updateDepartmentStaff(string $id, Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();
        $staff = User::find($id);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'STAFF_NOT_FOUND', 'message' => 'Staff account not found.'],
            ], 404);
        }

        if ($staff->department_id !== $hod->department_id) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'FORBIDDEN',
                    'message' => 'You can only manage staff in your own department.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            'name'      => 'nullable|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'role'      => ['nullable', 'string', Rule::in($this->allowedRoles)],
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $staff->update(array_filter($validated, fn($v) => !is_null($v)));

            AuditLog::create([
                'tenant_id'     => app('current_tenant_id'),
                'actor_id'      => $hod->id,
                'actor_role'    => $hod->role,
                'action'        => 'staff.updated',
                'resource_type' => 'User',
                'resource_id'   => $staff->id,
                'description'   => "HOD updated staff account: {$staff->name}",
                'ip_address'    => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $staff->fresh()->load('department'),
                'message' => 'Staff account updated successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('HOD failed to update staff account', [
                'error'    => $e->getMessage(),
                'staff_id' => $id,
                'hod_id'   => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'UPDATE_FAILED',
                    'message' => 'Failed to update staff account.',
                ],
            ], 500);
        }
    }

    /**
     * PATCH /api/v1/my-department/staff/{id}/deactivate
     * Deactivate a staff account in the HOD's department.
     */
    public function deactivateDepartmentStaff(string $id, Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();
        $staff = User::find($id);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'STAFF_NOT_FOUND', 'message' => 'Staff account not found.'],
            ], 404);
        }

        if ($staff->department_id !== $hod->department_id) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'FORBIDDEN',
                    'message' => 'You can only manage staff in your own department.',
                ],
            ], 403);
        }

        if (!$staff->is_active) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ALREADY_DEACTIVATED',
                    'message' => 'Staff account is already deactivated.',
                ],
            ], 400);
        }

        try {
            DB::beginTransaction();

            $staff->update([
                'is_active'     => false,
                'token_version' => $staff->token_version + 1,
            ]);

            AuditLog::create([
                'tenant_id'     => app('current_tenant_id'),
                'actor_id'      => $hod->id,
                'actor_role'    => $hod->role,
                'action'        => 'staff.deactivated',
                'resource_type' => 'User',
                'resource_id'   => $staff->id,
                'description'   => "HOD deactivated staff account: {$staff->name}. All sessions invalidated.",
                'ip_address'    => $request->ip(),
            ]);

            DB::commit();

            // Invalidate staff workload cache for the department
            app(\App\Services\DepartmentStatisticsService::class)->invalidateCache($hod->department_id);

            return response()->json([
                'success' => true,
                'data'    => $staff->fresh(),
                'message' => 'Staff account deactivated successfully. All active sessions have been invalidated.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('HOD failed to deactivate staff account', [
                'error'    => $e->getMessage(),
                'staff_id' => $id,
                'hod_id'   => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'DEACTIVATION_FAILED',
                    'message' => 'Failed to deactivate staff account.',
                ],
            ], 500);
        }
    }
}
