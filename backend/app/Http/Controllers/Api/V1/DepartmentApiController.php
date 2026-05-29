<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\VitalsRecorded;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DepartmentWorkingHours;
use App\Models\LabResult;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Models\PatientVitals;
use App\Models\UserNotificationPreferences;
use App\Services\DepartmentStatisticsService;
use App\Services\ExcelExportService;
use App\Services\PdfExportService;
use App\Services\ReportGenerationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DepartmentApiController extends Controller
{
    protected DepartmentStatisticsService $statisticsService;
    protected ReportGenerationService $reportService;
    protected PdfExportService $pdfService;
    protected ExcelExportService $excelService;

    public function __construct(
        DepartmentStatisticsService $statisticsService,
        ReportGenerationService $reportService,
        PdfExportService $pdfService,
        ExcelExportService $excelService
    ) {
        $this->statisticsService = $statisticsService;
        $this->reportService = $reportService;
        $this->pdfService = $pdfService;
        $this->excelService = $excelService;
    }

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
                    'message' => 'Only Heads of Department can access this feature.',
                ],
            ], 403);
        }

        return null;
    }

    /**
     * GET /department/api/dashboard/statistics
     * Get dashboard statistics for the HOD's department.
     */
    public function getDashboardStatistics(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        try {
            $statistics = $this->statisticsService->getQueueStatistics($hod->department_id);

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get dashboard statistics', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
                'department_id' => $hod->department_id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STATISTICS_ERROR',
                    'message' => 'Failed to retrieve dashboard statistics.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/dashboard/patient-flow
     * Get patient flow chart data for the last N days.
     */
    public function getPatientFlowChart(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();
        $days = $request->input('days', 7);

        try {
            $flowData = $this->statisticsService->getPatientFlowData($hod->department_id, $days);

            return response()->json([
                'success' => true,
                'data' => $flowData,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patient flow data', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
                'department_id' => $hod->department_id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FLOW_DATA_ERROR',
                    'message' => 'Failed to retrieve patient flow data.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/dashboard/staff-workload
     * Get staff workload chart data.
     */
    public function getStaffWorkloadChart(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        try {
            $workloadData = $this->statisticsService->getStaffWorkloadData($hod->department_id, $date);

            return response()->json([
                'success' => true,
                'data' => $workloadData,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get staff workload data', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
                'department_id' => $hod->department_id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WORKLOAD_DATA_ERROR',
                    'message' => 'Failed to retrieve staff workload data.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/dashboard/recent-activity
     * Get recent activity feed for the department.
     */
    public function getRecentActivity(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();
        $limit = $request->input('limit', 10);

        try {
            $activities = $this->statisticsService->getRecentActivityFeed($hod->department_id, $limit);

            return response()->json([
                'success' => true,
                'data' => $activities,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get recent activity', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
                'department_id' => $hod->department_id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACTIVITY_ERROR',
                    'message' => 'Failed to retrieve recent activity.',
                ],
            ], 500);
        }
    }

    /**
     * POST /department/api/patient-records/{id}/vitals
     * Record patient vitals.
     */
    public function recordVitals(string $patientRecordId, Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        // Find patient record
        $patientRecord = PatientRecord::find($patientRecordId);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATIENT_NOT_FOUND',
                    'message' => 'Patient record not found.',
                ],
            ], 404);
        }

        // Verify patient is in HOD's department
        if ($patientRecord->current_department_id !== $hod->department_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATIENT_NOT_IN_DEPARTMENT',
                    'message' => 'Patient is not currently in your department.',
                ],
            ], 403);
        }

        // Validate vitals data
        $validated = $request->validate([
            'blood_pressure' => 'nullable|string|max:20',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'pulse_rate' => 'nullable|integer|min:30|max:200',
            'respiratory_rate' => 'nullable|integer|min:5|max:60',
            'oxygen_saturation' => 'nullable|integer|min:50|max:100',
            'weight' => 'nullable|numeric|min:1|max:500',
            'height' => 'nullable|numeric|min:30|max:300',
            'blood_glucose' => 'nullable|integer|min:20|max:600',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Calculate BMI if weight and height are provided
            $bmi = null;
            if (isset($validated['weight']) && isset($validated['height'])) {
                $heightInMeters = $validated['height'] / 100;
                $bmi = round($validated['weight'] / ($heightInMeters * $heightInMeters), 2);
            }

            // Create vitals record
            $vitals = PatientVitals::create([
                'id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'patient_record_id' => $patientRecordId,
                'recorded_by' => $hod->id,
                'blood_pressure' => $validated['blood_pressure'] ?? null,
                'temperature' => $validated['temperature'] ?? null,
                'pulse_rate' => $validated['pulse_rate'] ?? null,
                'respiratory_rate' => $validated['respiratory_rate'] ?? null,
                'oxygen_saturation' => $validated['oxygen_saturation'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'height' => $validated['height'] ?? null,
                'bmi' => $bmi,
                'blood_glucose' => $validated['blood_glucose'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create patient journey event
            PatientJourneyEvent::create([
                'id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'patient_record_id' => $patientRecordId,
                'event_type' => 'vitals_recorded',
                'from_department_id' => $hod->department_id,
                'to_department_id' => $hod->department_id,
                'notes' => 'Vitals recorded by HOD',
                'metadata' => [
                    'vitals_id' => $vitals->id,
                    'blood_pressure' => $validated['blood_pressure'] ?? null,
                    'temperature' => $validated['temperature'] ?? null,
                    'pulse_rate' => $validated['pulse_rate'] ?? null,
                    'bmi' => $bmi,
                ],
                'performed_by' => $hod->id,
            ]);

            // Create audit log
            AuditLog::create([
                'id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'actor_id' => $hod->id,
                'actor_role' => $hod->role,
                'action' => 'vitals.recorded',
                'resource_type' => 'PatientVitals',
                'resource_id' => $vitals->id,
                'description' => "HOD recorded vitals for patient record {$patientRecordId}",
                'ip_address' => $request->ip(),
            ]);

            DB::commit();

            // Dispatch WebSocket event for real-time updates
            event(new VitalsRecorded($vitals, $patientRecord, $hod->name));

            // Invalidate department statistics cache
            app(\App\Services\DepartmentStatisticsService::class)->invalidateCache($hod->department_id);

            return response()->json([
                'success' => true,
                'data' => $vitals->load('recorder'),
                'message' => 'Vitals recorded successfully.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to record vitals', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
                'patient_record_id' => $patientRecordId,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VITALS_RECORDING_FAILED',
                    'message' => 'Failed to record vitals.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/patient-records/{id}/vitals
     * Get vitals history for a patient.
     */
    public function getVitalsHistory(string $patientRecordId): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        // Find patient record
        $patientRecord = PatientRecord::find($patientRecordId);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATIENT_NOT_FOUND',
                    'message' => 'Patient record not found.',
                ],
            ], 404);
        }

        // Verify patient is in HOD's department
        if ($patientRecord->current_department_id !== $hod->department_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATIENT_NOT_IN_DEPARTMENT',
                    'message' => 'Patient is not currently in your department.',
                ],
            ], 403);
        }

        try {
            $vitals = PatientVitals::with('recorder')
                ->where('patient_record_id', $patientRecordId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $vitals,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get vitals history', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
                'patient_record_id' => $patientRecordId,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VITALS_HISTORY_ERROR',
                    'message' => 'Failed to retrieve vitals history.',
                ],
            ], 500);
        }
    }

    // ==================== LAB RESULTS MANAGEMENT ====================

    /**
     * GET /department/api/lab-results
     * Get lab results for patients in the department.
     */
    public function getLabResults(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        try {
            $query = LabResult::with(['patientRecord.patient', 'technician'])
                ->whereHas('patientRecord', function ($q) use ($hod) {
                    $q->where('current_department_id', $hod->department_id);
                });

            // Apply filters
            if ($request->filled('patient_search')) {
                $search = $request->input('patient_search');
                $query->whereHas('patientRecord.patient', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('patient_number', 'like', "%{$search}%");
                });
            }

            if ($request->filled('test_type')) {
                $query->where('test_name', 'like', '%' . $request->input('test_type') . '%');
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $labResults = $query->orderBy('created_at', 'desc')->paginate(20);

            // Map to expected format
            $mapped = $labResults->getCollection()->map(function ($result) {
                return [
                    'id' => $result->id,
                    'patient_name' => $result->patientRecord?->patient?->full_name ?? 'Unknown',
                    'patient_number' => $result->patientRecord?->patient?->patient_number ?? 'N/A',
                    'patient_record_id' => $result->patient_record_id,
                    'test_type' => $result->test_name,
                    'test_name' => $result->test_name,
                    'result' => $result->result,
                    'unit' => $result->unit,
                    'reference_range' => $result->reference_range,
                    'result_status' => $result->status,
                    'notes' => $result->notes,
                    'technician_name' => $result->technician?->name,
                    'result_date' => $result->created_at,
                    'created_at' => $result->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $mapped,
                'meta' => [
                    'pagination' => [
                        'current_page' => $labResults->currentPage(),
                        'per_page' => $labResults->perPage(),
                        'total' => $labResults->total(),
                        'last_page' => $labResults->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get lab results', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LAB_RESULTS_ERROR',
                    'message' => 'Failed to retrieve lab results.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/patient-records/{id}/investigation-notes
     * Get investigation notes for a patient.
     */
    public function getInvestigationNotes(string $patientRecordId): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $patientRecord = PatientRecord::find($patientRecordId);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATIENT_NOT_FOUND',
                    'message' => 'Patient record not found.',
                ],
            ], 404);
        }

        if ($patientRecord->current_department_id !== $hod->department_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATIENT_NOT_IN_DEPARTMENT',
                    'message' => 'Patient is not currently in your department.',
                ],
            ], 403);
        }

        try {
            $investigationNotes = PatientJourneyEvent::with('performer')
                ->where('patient_record_id', $patientRecordId)
                ->whereNotNull('metadata->investigation_notes')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'notes' => $event->metadata['investigation_notes'] ?? '',
                        'requesting_doctor' => $event->metadata['requesting_doctor_name'] ?? $event->performer?->name ?? 'Unknown',
                        'timestamp' => $event->created_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $investigationNotes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get investigation notes', [
                'error' => $e->getMessage(),
                'patient_record_id' => $patientRecordId,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVESTIGATION_NOTES_ERROR',
                    'message' => 'Failed to retrieve investigation notes.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/lab-results/{id}/export-pdf
     * Export lab result to PDF.
     */
    public function exportLabResultPdf(string $labResultId): Response
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $labResult = LabResult::with(['patientRecord.patient', 'technician'])->find($labResultId);

        if (!$labResult) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LAB_RESULT_NOT_FOUND',
                    'message' => 'Lab result not found.',
                ],
            ], 404);
        }

        // Verify patient is in HOD's department
        if ($labResult->patientRecord->current_department_id !== $hod->department_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LAB_RESULT_NOT_IN_DEPARTMENT',
                    'message' => 'Lab result is not for a patient in your department.',
                ],
            ], 403);
        }

        try {
            // Create audit log
            AuditLog::create([
                'id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'actor_id' => $hod->id,
                'actor_role' => $hod->role,
                'action' => 'report.exported_pdf',
                'resource_type' => 'LabResult',
                'resource_id' => $labResultId,
                'description' => "HOD exported lab result {$labResultId} to PDF",
                'ip_address' => request()->ip(),
            ]);

            return $this->pdfService->generateLabResultPdf($labResult);
        } catch (\Exception $e) {
            Log::error('Failed to export lab result PDF', [
                'error' => $e->getMessage(),
                'lab_result_id' => $labResultId,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PDF_GENERATION_FAILED',
                    'message' => 'Failed to generate PDF.',
                ],
            ], 500);
        }
    }

    // ==================== PRESCRIPTION MANAGEMENT ====================

    /**
     * GET /department/api/prescriptions
     * Get prescriptions for patients in the department.
     */
    public function getPrescriptions(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        try {
            $query = PatientJourneyEvent::with(['patientRecord.patient', 'performer'])
                ->whereNotNull('metadata->prescription_notes')
                ->whereHas('patientRecord', function ($q) use ($hod) {
                    $q->where('current_department_id', $hod->department_id);
                });

            // Apply filters
            if ($request->filled('patient_search')) {
                $search = $request->input('patient_search');
                $query->whereHas('patientRecord.patient', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('patient_number', 'like', "%{$search}%");
                });
            }

            if ($request->filled('doctor_search')) {
                $search = $request->input('doctor_search');
                $query->whereHas('performer', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $prescriptions = $query->orderBy('created_at', 'desc')->paginate(20);

            // Map to expected format
            $mapped = $prescriptions->getCollection()->map(function ($event) {
                $metadata = is_array($event->metadata) ? $event->metadata : json_decode($event->metadata, true);
                return [
                    'id' => $event->id,
                    'patient_name' => $event->patientRecord?->patient?->full_name ?? 'Unknown',
                    'patient_number' => $event->patientRecord?->patient?->patient_number ?? 'N/A',
                    'patient_record_id' => $event->patient_record_id,
                    'doctor_name' => $event->performer?->name ?? 'Unknown',
                    'prescription_notes' => $metadata['prescription_notes'] ?? null,
                    'created_at' => $event->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $mapped,
                'meta' => [
                    'pagination' => [
                        'current_page' => $prescriptions->currentPage(),
                        'per_page' => $prescriptions->perPage(),
                        'total' => $prescriptions->total(),
                        'last_page' => $prescriptions->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get prescriptions', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRESCRIPTIONS_ERROR',
                    'message' => 'Failed to retrieve prescriptions.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/patient-records/{id}/prescriptions
     * Get prescription history for a patient.
     */
    public function getPrescriptionHistory(string $patientRecordId): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $patientRecord = PatientRecord::find($patientRecordId);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATIENT_NOT_FOUND',
                    'message' => 'Patient record not found.',
                ],
            ], 404);
        }

        if ($patientRecord->current_department_id !== $hod->department_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PATIENT_NOT_IN_DEPARTMENT',
                    'message' => 'Patient is not currently in your department.',
                ],
            ], 403);
        }

        try {
            $prescriptions = PatientJourneyEvent::with('performer')
                ->where('patient_record_id', $patientRecordId)
                ->whereNotNull('metadata->prescription_notes')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $prescriptions,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get prescription history', [
                'error' => $e->getMessage(),
                'patient_record_id' => $patientRecordId,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRESCRIPTION_HISTORY_ERROR',
                    'message' => 'Failed to retrieve prescription history.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/prescriptions/{eventId}/export-pdf
     * Export prescription to PDF.
     */
    public function exportPrescriptionPdf(string $eventId): Response
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $event = PatientJourneyEvent::with(['patientRecord.patient', 'performer'])->find($eventId);

        if (!$event) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRESCRIPTION_NOT_FOUND',
                    'message' => 'Prescription not found.',
                ],
            ], 404);
        }

        // Verify patient is in HOD's department
        if ($event->patientRecord->current_department_id !== $hod->department_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRESCRIPTION_NOT_IN_DEPARTMENT',
                    'message' => 'Prescription is not for a patient in your department.',
                ],
            ], 403);
        }

        try {
            // Create audit log
            AuditLog::create([
                'id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'actor_id' => $hod->id,
                'actor_role' => $hod->role,
                'action' => 'report.exported_pdf',
                'resource_type' => 'Prescription',
                'resource_id' => $eventId,
                'description' => "HOD exported prescription {$eventId} to PDF",
                'ip_address' => request()->ip(),
            ]);

            return $this->pdfService->generatePrescriptionPdf($event);
        } catch (\Exception $e) {
            Log::error('Failed to export prescription PDF', [
                'error' => $e->getMessage(),
                'event_id' => $eventId,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PDF_GENERATION_FAILED',
                    'message' => 'Failed to generate PDF.',
                ],
            ], 500);
        }
    }

    // ==================== REPORTING ====================

    /**
     * GET /department/api/reports/daily-summary
     * Generate daily summary report.
     */
    public function getDailySummaryReport(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        try {
            $report = $this->reportService->generateDailySummary($hod->department_id, $date);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate daily summary report', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
                'date' => $date->format('Y-m-d'),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REPORT_GENERATION_FAILED',
                    'message' => 'Failed to generate daily summary report.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/reports/staff-performance
     * Generate staff performance report.
     */
    public function getStaffPerformanceReport(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        // Validate date range
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        // Check maximum 365 days
        if ($startDate->diffInDays($endDate) > 365) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DATE_RANGE_EXCEEDED',
                    'message' => 'Date range cannot exceed 365 days.',
                ],
            ], 422);
        }

        try {
            $report = $this->reportService->generateStaffPerformance($hod->department_id, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate staff performance report', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REPORT_GENERATION_FAILED',
                    'message' => 'Failed to generate staff performance report.',
                ],
            ], 500);
        }
    }

    /**
     * GET /department/api/reports/efficiency-metrics
     * Generate department efficiency metrics report.
     */
    public function getDepartmentEfficiencyReport(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        // Validate date range
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        // Check maximum 365 days
        if ($startDate->diffInDays($endDate) > 365) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DATE_RANGE_EXCEEDED',
                    'message' => 'Date range cannot exceed 365 days.',
                ],
            ], 422);
        }

        try {
            $report = $this->reportService->generateEfficiencyMetrics($hod->department_id, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate efficiency metrics report', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REPORT_GENERATION_FAILED',
                    'message' => 'Failed to generate efficiency metrics report.',
                ],
            ], 500);
        }
    }

    /**
     * POST /department/api/reports/export-pdf
     * Export report to PDF.
     */
    public function exportReportPdf(Request $request): Response
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $validated = $request->validate([
            'report_type' => 'required|in:daily_summary,staff_performance,efficiency_metrics',
            'report_data' => 'required|array',
        ]);

        try {
            $metadata = [
                'tenant_id' => app('current_tenant_id'),
                'report_title' => ucwords(str_replace('_', ' ', $validated['report_type'])),
            ];

            // Create audit log
            AuditLog::create([
                'id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'actor_id' => $hod->id,
                'actor_role' => $hod->role,
                'action' => 'report.exported_pdf',
                'resource_type' => 'Report',
                'resource_id' => null,
                'description' => "HOD exported {$validated['report_type']} report to PDF",
                'ip_address' => $request->ip(),
                'metadata' => ['report_type' => $validated['report_type']],
            ]);

            return $this->pdfService->generateReportPdf(
                $validated['report_type'],
                $validated['report_data'],
                $metadata
            );
        } catch (\Exception $e) {
            Log::error('Failed to export report PDF', [
                'error' => $e->getMessage(),
                'report_type' => $validated['report_type'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PDF_GENERATION_FAILED',
                    'message' => 'Failed to generate PDF.',
                ],
            ], 500);
        }
    }

    /**
     * POST /department/api/reports/export-excel
     * Export report to Excel.
     */
    public function exportReportExcel(Request $request): Response
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $validated = $request->validate([
            'report_type' => 'required|in:daily_summary,staff_performance,efficiency_metrics',
            'report_data' => 'required|array',
        ]);

        try {
            $metadata = [
                'tenant_id' => app('current_tenant_id'),
                'report_title' => ucwords(str_replace('_', ' ', $validated['report_type'])),
            ];

            // Create audit log
            AuditLog::create([
                'id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'actor_id' => $hod->id,
                'actor_role' => $hod->role,
                'action' => 'report.exported_excel',
                'resource_type' => 'Report',
                'resource_id' => null,
                'description' => "HOD exported {$validated['report_type']} report to Excel",
                'ip_address' => $request->ip(),
                'metadata' => ['report_type' => $validated['report_type']],
            ]);

            return $this->excelService->generateReportExcel(
                $validated['report_type'],
                $validated['report_data'],
                $metadata
            );
        } catch (\Exception $e) {
            Log::error('Failed to export report Excel', [
                'error' => $e->getMessage(),
                'report_type' => $validated['report_type'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EXCEL_EXPORT_FAILED',
                    'message' => 'Failed to export to Excel.',
                ],
            ], 500);
        }
    }

    // ==================== DEPARTMENT SETTINGS ====================

    /**
     * PUT /department/api/settings/info
     * Update department information.
     */
    public function updateDepartmentInfo(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ]);

        try {
            $department = Department::find($hod->department_id);

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DEPARTMENT_NOT_FOUND',
                        'message' => 'Department not found.',
                    ],
                ], 404);
            }

            // Check name uniqueness if name is being updated
            if (isset($validated['name']) && $validated['name'] !== $department->name) {
                $exists = Department::where('name', $validated['name'])
                    ->where('id', '!=', $department->id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'DUPLICATE_NAME',
                            'message' => 'Department name already exists.',
                        ],
                    ], 422);
                }
            }

            DB::beginTransaction();

            $department->update($validated);

            // Create audit log
            AuditLog::create([
                'id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'actor_id' => $hod->id,
                'actor_role' => $hod->role,
                'action' => 'department.updated',
                'resource_type' => 'Department',
                'resource_id' => $department->id,
                'description' => "HOD updated department information",
                'ip_address' => $request->ip(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $department,
                'message' => 'Department information updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update department info', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => 'Failed to update department information.',
                ],
            ], 500);
        }
    }

    /**
     * PUT /department/api/settings/working-hours
     * Update department working hours.
     */
    public function updateWorkingHours(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $validated = $request->validate([
            'working_hours' => 'required|array',
            'working_hours.*.day_of_week' => 'required|integer|min:0|max:6',
            'working_hours.*.is_open' => 'required|boolean',
            'working_hours.*.opening_time' => 'nullable|date_format:H:i',
            'working_hours.*.closing_time' => 'nullable|date_format:H:i',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['working_hours'] as $hours) {
                // Validate opening time is before closing time
                if ($hours['is_open'] && isset($hours['opening_time']) && isset($hours['closing_time'])) {
                    if ($hours['opening_time'] >= $hours['closing_time']) {
                        return response()->json([
                            'success' => false,
                            'error' => [
                                'code' => 'INVALID_HOURS',
                                'message' => 'Opening time must be before closing time.',
                            ],
                        ], 422);
                    }
                }

                DepartmentWorkingHours::updateOrCreate(
                    [
                        'department_id' => $hod->department_id,
                        'day_of_week' => $hours['day_of_week'],
                    ],
                    [
                        'tenant_id' => app('current_tenant_id'),
                        'is_open' => $hours['is_open'],
                        'opening_time' => $hours['opening_time'] ?? null,
                        'closing_time' => $hours['closing_time'] ?? null,
                    ]
                );
            }

            // Create audit log
            AuditLog::create([
                'id' => Str::uuid(),
                'tenant_id' => app('current_tenant_id'),
                'actor_id' => $hod->id,
                'actor_role' => $hod->role,
                'action' => 'working_hours.updated',
                'resource_type' => 'DepartmentWorkingHours',
                'resource_id' => $hod->department_id,
                'description' => "HOD updated department working hours",
                'ip_address' => $request->ip(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Working hours updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update working hours', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => 'Failed to update working hours.',
                ],
            ], 500);
        }
    }

    /**
     * PUT /department/api/settings/notifications
     * Update notification preferences.
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        if ($error = $this->ensureHod()) {
            return $error;
        }

        $hod = request()->user() ?? auth('web')->user();

        $validated = $request->validate([
            'patient_arrivals_enabled' => 'sometimes|boolean',
            'staff_actions_enabled' => 'sometimes|boolean',
            'system_alerts_enabled' => 'sometimes|boolean',
            'email_enabled' => 'sometimes|boolean',
            'sms_enabled' => 'sometimes|boolean',
            'in_app_enabled' => 'sometimes|boolean',
        ]);

        try {
            $preferences = UserNotificationPreferences::updateOrCreate(
                ['user_id' => $hod->id],
                array_merge(
                    ['tenant_id' => app('current_tenant_id')],
                    $validated
                )
            );

            return response()->json([
                'success' => true,
                'data' => $preferences,
                'message' => 'Notification preferences updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update notification preferences', [
                'error' => $e->getMessage(),
                'hod_id' => $hod->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => 'Failed to update notification preferences.',
                ],
            ], 500);
        }
    }
}
