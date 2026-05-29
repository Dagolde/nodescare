<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HospitalAdminWebController extends Controller
{
    /**
     * Hospital Admin dashboard.
     */
    public function dashboard()
    {
        return Inertia::render('HospitalAdmin/Dashboard');
    }

    /**
     * Department management page.
     */
    public function departments(Request $request)
    {
        $query = Department::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $departments = $query->orderBy('name')->paginate(20);

        return Inertia::render('HospitalAdmin/Departments', [
            'departments' => $departments,
            'filters' => $request->only(['is_active', 'type']),
        ]);
    }

    /**
     * Staff management page.
     */
    public function staff(Request $request)
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
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('HospitalAdmin/Staff', [
            'staff' => $staff,
            'departments' => $departments,
            'filters' => $request->only(['is_active', 'role', 'department_id']),
        ]);
    }

    /**
     * Workflow templates page.
     */
    public function workflowTemplates(Request $request)
    {
        $query = WorkflowTemplate::with(['steps.department', 'creator']);

        if ($request->has('is_default')) {
            $query->where('is_default', $request->boolean('is_default'));
        }

        $templates = $query->orderBy('created_at', 'desc')->paginate(20);
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('HospitalAdmin/WorkflowTemplates', [
            'templates' => $templates,
            'departments' => $departments,
            'filters' => $request->only(['is_default']),
        ]);
    }

    /**
     * Department activity reports page.
     */
    public function departmentReports(Request $request)
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('HospitalAdmin/DepartmentReports', [
            'departments' => $departments,
            'filters' => $request->only(['start_date', 'end_date', 'department_id']),
        ]);
    }

    /**
     * Financial reports page.
     */
    public function financialReports(Request $request)
    {
        return Inertia::render('HospitalAdmin/FinancialReports', [
            'filters' => $request->only(['start_date', 'end_date']),
        ]);
    }

    /**
     * Audit logs page.
     */
    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('actor');

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        if ($request->has('action')) {
            $query->where('action', 'like', "%{$request->input('action')}%");
        }

        if ($request->has('actor_id')) {
            $query->where('actor_id', $request->input('actor_id'));
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('HospitalAdmin/AuditLogs', [
            'logs' => $logs,
            'users' => $users,
            'filters' => $request->only(['start_date', 'end_date', 'action', 'actor_id']),
        ]);
    }

    /**
     * Ward management page — admitted patients overview.
     */
    public function ward()
    {
        $admittedPatients = \App\Models\PatientRecord::with(['patient', 'currentDepartment'])
            ->where('status', 'admitted')
            ->orderBy('admitted_at', 'asc')
            ->get();

        return Inertia::render('HospitalAdmin/Ward', [
            'admittedPatients' => $admittedPatients->map(fn($p) => [
                'id'                    => $p->id,
                'admitted_at'           => $p->admitted_at?->format('Y-m-d H:i:s'),
                'current_department'    => $p->currentDepartment?->name,
                'patient'               => $p->patient ? [
                    'id'              => $p->patient->id,
                    'patient_number'  => $p->patient->patient_number,
                    'full_name'       => $p->patient->full_name,
                    'date_of_birth'   => $p->patient->date_of_birth,
                    'gender'          => $p->patient->gender,
                    'phone'           => $p->patient->phone,
                    'chief_complaint' => $p->patient->chief_complaint,
                ] : null,
            ]),
            'totalAdmitted' => $admittedPatients->count(),
        ]);
    }

    /**
     * Wallet page.
     */
    public function wallet()
    {
        $paystackPublicKey = config('services.paystack.public_key', '');

        return Inertia::render('HospitalAdmin/Wallet', [
            'paystackPublicKey' => $paystackPublicKey,
        ]);
    }
}
