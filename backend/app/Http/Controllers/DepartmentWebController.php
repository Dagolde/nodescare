<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PatientRecord;
use App\Services\DepartmentStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class DepartmentWebController extends Controller
{
    protected DepartmentStatisticsService $statisticsService;

    public function __construct(DepartmentStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * HOD Dashboard page - shows statistics, charts, and recent activity.
     */
    public function dashboard(Request $request)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->department_id) {
            return redirect('/hospital-admin/login')
                ->withErrors(['error' => 'You must be assigned to a department to access this page.']);
        }

        // Verify user is HOD
        if (!$user->isHod()) {
            return redirect('/department/queue')
                ->withErrors(['error' => 'Only Heads of Department can access the dashboard.']);
        }

        $department = Department::find($user->department_id);

        // Get dashboard statistics
        $statistics = $this->statisticsService->getQueueStatistics($user->department_id);
        $patientFlowData = $this->statisticsService->getPatientFlowData($user->department_id, 7);
        $staffWorkloadData = $this->statisticsService->getStaffWorkloadData($user->department_id, now());
        $recentActivity = $this->statisticsService->getRecentActivityFeed($user->department_id, 10);

        return Inertia::render('Department/Dashboard', [
            'statistics' => $statistics,
            'patientFlowData' => $patientFlowData,
            'staffWorkloadData' => $staffWorkloadData,
            'recentActivity' => $recentActivity,
            'department' => $department,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'is_hod' => $user->isHod(),
            ],
        ]);
    }

    /**
     * Department queue page - shows patient records assigned to the authenticated user's department.
     */
    public function queue(Request $request)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->department_id) {
            return redirect('/hospital-admin/login')
                ->withErrors(['error' => 'You must be assigned to a department to access this page.']);
        }

        $department = Department::find($user->department_id);

        $query = PatientRecord::with([
            'patient',
            'currentDepartment',
            'workflowTemplate',
        ])
            ->where('current_department_id', $user->department_id)
            ->where('status', '!=', 'pending_payment');

        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('patient_number', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        $queueRecords = $query->orderBy('created_at', 'asc')->paginate(20);

        return Inertia::render('Department/Queue', [
            'queueRecords' => $queueRecords,
            'department' => $department,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department_id' => $user->department_id,
            ],
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Patient detail page - shows full patient record with journey history.
     */
    public function patientDetail(Request $request, string $patientRecordId)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->department_id) {
            return redirect('/hospital-admin/login')
                ->withErrors(['error' => 'You must be assigned to a department to access this page.']);
        }

        $patientRecord = PatientRecord::with([
            'patient',
            'currentDepartment',
            'workflowTemplate.steps.department',
            'journeyEvents.fromDepartment',
            'journeyEvents.toDepartment',
            'journeyEvents.performer',
        ])->findOrFail($patientRecordId);

        // Get available departments for doctor routing
        $departments = Department::where('is_active', true)
            ->where('id', '!=', $user->department_id)
            ->get(['id', 'name', 'type', 'code']);

        return Inertia::render('Department/PatientDetail', [
            'patientRecord' => $patientRecord,
            'department' => Department::find($user->department_id),
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department_id' => $user->department_id,
            ],
            'availableDepartments' => $departments,
        ]);
    }

    /**
     * Vitals Recording page - record and view patient vitals.
     */
    public function vitalsRecording(Request $request, string $patientRecordId = null)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->department_id) {
            return redirect('/hospital-admin/login')
                ->withErrors(['error' => 'You must be assigned to a department to access this page.']);
        }

        // Verify user is HOD
        if (!$user->isHod()) {
            return redirect('/department/queue')
                ->withErrors(['error' => 'Only Heads of Department can record vitals.']);
        }

        $department = Department::find($user->department_id);
        $patientRecord = null;
        $vitalsHistory = [];

        if ($patientRecordId) {
            $patientRecord = PatientRecord::with('patient')->find($patientRecordId);
            
            if ($patientRecord && $patientRecord->current_department_id === $user->department_id) {
                $vitalsHistory = \App\Models\PatientVitals::with('recorder')
                    ->where('patient_record_id', $patientRecordId)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }

        return Inertia::render('Department/VitalsRecording', [
            'patientRecord' => $patientRecord,
            'vitalsHistory' => $vitalsHistory,
            'department' => $department,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'is_hod' => $user->isHod(),
            ],
        ]);
    }

    /**
     * HOD Staff Management page.
     */
    public function staffManagement(Request $request)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->department_id) {
            return redirect('/hospital-admin/login')
                ->withErrors(['error' => 'You must be assigned to a department to access this page.']);
        }

        if (!$user->isHod()) {
            return redirect('/department/queue')
                ->withErrors(['error' => 'Only Heads of Department can manage staff.']);
        }

        $department = Department::find($user->department_id);

        return Inertia::render('Department/StaffManagement', [
            'department' => $department,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'is_hod' => $user->isHod(),
            ],
        ]);
    }

    /**
     * HOD Lab Results page.
     */
    public function labResults(Request $request)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->department_id) {
            return redirect('/hospital-admin/login')
                ->withErrors(['error' => 'You must be assigned to a department to access this page.']);
        }

        if (!$user->isHod()) {
            return redirect('/department/queue')
                ->withErrors(['error' => 'Only Heads of Department can access lab results.']);
        }

        return Inertia::render('Department/LabResultsManagement', [
            'department' => Department::find($user->department_id),
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'is_hod' => $user->isHod(),
            ],
        ]);
    }

    /**
     * HOD Prescriptions page.
     */
    public function prescriptions(Request $request)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->department_id) {
            return redirect('/hospital-admin/login')
                ->withErrors(['error' => 'You must be assigned to a department to access this page.']);
        }

        if (!$user->isHod()) {
            return redirect('/department/queue')
                ->withErrors(['error' => 'Only Heads of Department can access prescriptions.']);
        }

        return Inertia::render('Department/PrescriptionManagement', [
            'department' => Department::find($user->department_id),
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'is_hod' => $user->isHod(),
            ],
        ]);
    }

    /**
     * HOD Reports page - generate and export department reports.
     */
    public function reports(Request $request)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->department_id) {
            return redirect('/hospital-admin/login')
                ->withErrors(['error' => 'You must be assigned to a department to access this page.']);
        }

        // Verify user is HOD
        if (!$user->isHod()) {
            return redirect('/department/queue')
                ->withErrors(['error' => 'Only Heads of Department can access reports.']);
        }

        $department = Department::find($user->department_id);

        return Inertia::render('Department/Reports', [
            'department' => $department,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'is_hod' => $user->isHod(),
            ],
        ]);
    }

    /**
     * HOD Settings page - manage department info, working hours, and notifications.
     */
    public function settings(Request $request)
    {
        $user = $this->getAuthenticatedUser($request);

        if (!$user || !$user->department_id) {
            return redirect('/hospital-admin/login')
                ->withErrors(['error' => 'You must be assigned to a department to access this page.']);
        }

        // Verify user is HOD
        if (!$user->isHod()) {
            return redirect('/department/queue')
                ->withErrors(['error' => 'Only Heads of Department can access settings.']);
        }

        $department = Department::find($user->department_id);
        $workingHours = \App\Models\DepartmentWorkingHours::where('department_id', $user->department_id)
            ->orderBy('day_of_week')
            ->get();
        $notificationPreferences = \App\Models\UserNotificationPreferences::where('user_id', $user->id)->first();

        return Inertia::render('Department/Settings', [
            'department' => $department,
            'workingHours' => $workingHours,
            'notificationPreferences' => $notificationPreferences,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'is_hod' => $user->isHod(),
            ],
        ]);
    }

    /**
     * Resolve the authenticated user from session.
     */
    protected function getAuthenticatedUser(Request $request)
    {
        return Auth::guard('web')->user();
    }
}
