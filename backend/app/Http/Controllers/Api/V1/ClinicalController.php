<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DispensingRecord;
use App\Models\LabResult;
use App\Models\Notification;
use App\Models\PatientDocument;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClinicalController extends Controller
{
    protected WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    // -------------------------------------------------------------------------
    // Task 12.1 — Doctor Appointment Endpoints
    // -------------------------------------------------------------------------

    /**
     * Book a new appointment.
     * POST /api/v1/appointments
     *
     * Requirements: 7.7, 7.8, 7.9
     */
    public function bookAppointment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id'        => 'required|uuid|exists:patients,id',
            'patient_record_id' => 'nullable|uuid|exists:patient_records,id',
            'scheduled_at'      => 'required|date',
            'notes'             => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        // Requirement 7.9 — reject past appointments
        if (now()->gte($request->input('scheduled_at'))) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'PAST_APPOINTMENT',
                    'message' => 'Appointment date must be in the future.',
                ],
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            DB::beginTransaction();

            $appointment = Appointment::create([
                'id'           => \Illuminate\Support\Str::uuid(),
                'tenant_id'    => app('current_tenant_id'),
                'patient_id'   => $request->input('patient_id'),
                'doctor_id'    => $user->id,
                'department_id' => $user->department_id,
                'scheduled_at' => $request->input('scheduled_at'),
                'status'       => 'scheduled',
                'notes'        => $request->input('notes'),
            ]);

            AuditLog::create([
                'tenant_id'     => app('current_tenant_id'),
                'actor_id'      => $user->id,
                'actor_role'    => $user->role,
                'action'        => 'appointment.created',
                'resource_type' => 'Appointment',
                'resource_id'   => $appointment->id,
                'description'   => "Appointment booked by Dr. {$user->name}",
                'ip_address'    => $request->ip(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $appointment->load(['patient', 'doctor', 'department']),
                'message' => 'Appointment booked successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'APPOINTMENT_BOOKING_ERROR',
                    'message' => 'Failed to book appointment',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * List appointments for the authenticated doctor.
     * GET /api/v1/appointments
     *
     * Requirements: 7.7
     */
    public function listAppointments(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $appointments = Appointment::where('doctor_id', $user->id)
            ->with(['patient', 'doctor', 'department'])
            ->orderBy('scheduled_at', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $appointments->items(),
            'meta'    => [
                'pagination' => [
                    'current_page' => $appointments->currentPage(),
                    'per_page'     => $appointments->perPage(),
                    'total'        => $appointments->total(),
                    'last_page'    => $appointments->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get ALL appointments (upcoming + past) for the hospital — used by doctor and front desk.
     * GET /api/v1/appointments/all
     */
    public function allAppointments(Request $request): JsonResponse
    {
        $appointments = Appointment::with(['patient', 'doctor', 'department'])
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $appointments->map(fn($a) => array_merge($a->toArray(), [
                'doctor_name'     => $a->doctor->name ?? null,
                'department_name' => $a->department->name ?? null,
            ])),
            'meta'    => ['total' => $appointments->count()],
        ]);
    }

    /**
     * Get today's appointments for front desk.
     * GET /api/v1/appointments/today
     */
    public function todaysAppointments(Request $request): JsonResponse
    {
        $today = now()->startOfDay();
        $endOfDay = now()->endOfDay();

        $appointments = Appointment::whereBetween('scheduled_at', [$today, $endOfDay])
            ->with(['patient', 'doctor', 'department'])
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $appointments->map(fn($a) => array_merge($a->toArray(), [
                'doctor_name'     => $a->doctor->name ?? null,
                'department_name' => $a->department->name ?? null,
            ])),
            'meta' => [
                'date'  => $today->format('Y-m-d'),
                'total' => $appointments->count(),
            ],
        ]);
    }

    /**
     * Update an appointment.
     * PATCH /api/v1/appointments/{id}
     *
     * Requirements: 7.7
     */
    public function updateAppointment(string $id, Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Appointment not found',
                ],
            ], 404);
        }

        // Only the owning doctor may update
        if ($appointment->doctor_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'FORBIDDEN',
                    'message' => 'You are not authorised to update this appointment',
                ],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'sometimes|date',
            'status'       => 'sometimes|in:scheduled,completed,cancelled,no_show',
            'notes'        => 'sometimes|nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        // Requirement 7.9 — reject past scheduled_at if provided
        if ($request->has('scheduled_at') && now()->gte($request->input('scheduled_at'))) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'PAST_APPOINTMENT',
                    'message' => 'Appointment date must be in the future.',
                ],
            ], 422);
        }

        try {
            $appointment->update($request->only(['scheduled_at', 'status', 'notes']));

            AuditLog::create([
                'actor_id'      => $user->id,
                'actor_role'    => $user->role,
                'action'        => 'appointment.updated',
                'resource_type' => 'Appointment',
                'resource_id'   => $appointment->id,
                'description'   => "Appointment {$appointment->id} updated by Dr. {$user->name}",
                'ip_address'    => $request->ip(),
                'metadata'      => $request->only(['scheduled_at', 'status', 'notes']),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $appointment->fresh(['patient', 'patientRecord', 'doctor']),
                'message' => 'Appointment updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'APPOINTMENT_UPDATE_ERROR',
                    'message' => 'Failed to update appointment',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Task 12.2 — Pharmacy Dispensing Endpoint
    // -------------------------------------------------------------------------

    /**
     * Record medication dispensing for a patient record.
     * POST /api/v1/patient-records/{id}/dispensing
     *
     * Requirements: 8.1, 8.2, 8.3, 8.4
     */
    public function recordDispensing(string $id, Request $request): JsonResponse
    {
        $patientRecord = PatientRecord::find($id);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Patient record not found',
                ],
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'medications' => 'required|array|min:1',
            'medications.*.drug_name' => 'required|string|max:255',
            'medications.*.dosage' => 'required|string|max:100',
            'medications.*.quantity' => 'required|string|max:100',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            DB::beginTransaction();

            // Requirement 8.4 — check for prescription notes in journey events
            $hasPrescriptionNotes = PatientJourneyEvent::where('patient_record_id', $patientRecord->id)
                ->whereNotNull('metadata->prescription_notes')
                ->exists();

            if (!$hasPrescriptionNotes) {
                // Log anomaly to audit log
                AuditLog::create([
                    'tenant_id'     => app('current_tenant_id'),
                    'actor_id'      => $user->id,
                    'actor_role'    => $user->role,
                    'action'        => 'anomaly.missing_prescription_notes',
                    'resource_type' => 'PatientRecord',
                    'resource_id'   => $patientRecord->id,
                    'description'   => "Dispensing recorded for patient record {$patientRecord->id} without prescription notes",
                    'ip_address'    => $request->ip(),
                    'metadata'      => [
                        'patient_record_id' => $patientRecord->id,
                        'pharmacist_id'     => $user->id,
                    ],
                ]);
            }

            // Record dispensing
            $dispensingRecord = DispensingRecord::create([
                'id'                => \Illuminate\Support\Str::uuid(),
                'tenant_id'         => app('current_tenant_id'),
                'patient_record_id' => $patientRecord->id,
                'pharmacist_id'     => $user->id,
                'medications'       => $request->input('medications', []),
                'notes'             => $request->input('notes'),
            ]);

            PatientJourneyEvent::create([
                'id'                 => \Illuminate\Support\Str::uuid(),
                'tenant_id'          => app('current_tenant_id'),
                'patient_record_id'  => $patientRecord->id,
                'from_department_id' => $user->department_id,
                'to_department_id'   => $user->department_id,
                'event_type'         => 'note_added',
                'performed_by'       => $user->id,
                'notes'              => 'Dispensing recorded.',
            ]);

            AuditLog::create([
                'tenant_id'     => app('current_tenant_id'),
                'actor_id'      => $user->id,
                'actor_role'    => $user->role,
                'action'        => 'dispensing.recorded',
                'resource_type' => 'DispensingRecord',
                'resource_id'   => $dispensingRecord->id,
                'description'   => "Dispensing recorded by {$user->name}",
                'ip_address'    => $request->ip(),
            ]);

            DB::commit();

            // Deduct pharmacy fee from hospital wallet
            try {
                $walletService = app(\App\Services\WalletService::class);
                $tenantId      = app('current_tenant_id');
                $tenant        = \App\Models\Tenant::find($tenantId);
                $walletService->deductForPharmacy(
                    $tenantId,
                    $patientRecord->patient_id,
                    null,
                    $tenant?->plan_id
                );
            } catch (\Exception $walletEx) {
                \Log::warning('Wallet deduction failed after dispensing', [
                    'dispensing_id' => $dispensingRecord->id,
                    'error'         => $walletEx->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => $dispensingRecord->load(['patientRecord', 'pharmacist']),
                'message' => 'Dispensing recorded successfully',
                'warning' => $hasPrescriptionNotes ? null : 'No prescription notes found for this patient record.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'DISPENSING_ERROR',
                    'message' => 'Failed to record dispensing',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Task 12.3 — Laboratory Results Endpoint
    // -------------------------------------------------------------------------

    /**
     * Record lab results for a patient record.
     * Accepts either a single result (legacy) or a batch via `results[]`.
     * POST /api/v1/patient-records/{id}/lab-results
     *
     * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5
     */
    public function recordLabResults(string $id, Request $request): JsonResponse
    {
        $patientRecord = PatientRecord::find($id);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient record not found'],
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            // Batch mode
            'results'                          => 'sometimes|array|min:1',
            'results.*.test_name'              => 'required_with:results|string|max:255',
            'results.*.result'                 => 'required_with:results|string',
            'results.*.reference_range'        => 'nullable|string|max:255',
            'results.*.result_document_key'    => 'nullable|string|max:500',
            'results.*.result_document_filename' => 'nullable|string|max:255',
            // Legacy single-result mode
            'test_name'           => 'required_without:results|string|max:255',
            'result'              => 'required_without:results|string',
            'reference_range'     => 'nullable|string|max:255',
            'result_document_key' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'The given data was invalid.', 'details' => $validator->errors()],
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Normalise to array of results
        if ($request->has('results')) {
            $resultsInput = $request->input('results');
        } else {
            $resultsInput = [[
                'test_name'                => $request->input('test_name'),
                'result'                   => $request->input('result'),
                'reference_range'          => $request->input('reference_range'),
                'result_document_key'      => $request->input('result_document_key'),
                'result_document_filename' => $request->input('result_document_filename'),
            ]];
        }

        try {
            DB::beginTransaction();

            $hasInvestigationNotes = PatientJourneyEvent::where('patient_record_id', $patientRecord->id)
                ->whereNotNull('metadata->investigation_notes')
                ->exists();

            // Enforce: lab bill must exist and be paid before results can be submitted
            $hasLabBill = PatientJourneyEvent::where('patient_record_id', $patientRecord->id)
                ->whereNotNull('metadata->lab_bill_items')
                ->exists();
            $hasPaidLabInvoice = \App\Models\Invoice::where('patient_record_id', $patientRecord->id)
                ->where('status', 'paid')
                ->exists();

            if (!$hasLabBill || !$hasPaidLabInvoice) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code'    => 'BILL_REQUIRED',
                        'message' => 'Lab bill must be created and payment confirmed before submitting results.',
                    ],
                ], 422);
            }

            if (!$hasInvestigationNotes) {
                AuditLog::create([
                    'tenant_id'     => app('current_tenant_id'),
                    'actor_id'      => $user->id,
                    'actor_role'    => $user->role,
                    'action'        => 'anomaly.missing_investigation_notes',
                    'resource_type' => 'PatientRecord',
                    'resource_id'   => $patientRecord->id,
                    'description'   => "Lab results recorded without investigation request notes",
                    'ip_address'    => $request->ip(),
                ]);
            }

            $savedResults = [];

            foreach ($resultsInput as $item) {
                $labResult = LabResult::create([
                    'id'                => \Illuminate\Support\Str::uuid(),
                    'tenant_id'         => app('current_tenant_id'),
                    'patient_record_id' => $patientRecord->id,
                    'technician_id'     => $user->id,
                    'test_name'         => $item['test_name'],
                    'result'            => $item['result'],
                    'reference_range'   => $item['reference_range'] ?? null,
                    'status'            => 'completed',
                ]);

                if (!empty($item['result_document_key'])) {
                    PatientDocument::create([
                        'id'                => \Illuminate\Support\Str::uuid(),
                        'tenant_id'         => app('current_tenant_id'),
                        'patient_id'        => $patientRecord->patient_id,
                        'uploader_id'       => $user->id,
                        'document_type'     => 'lab_result',
                        'object_key'        => $item['result_document_key'],
                        'original_filename' => $item['result_document_filename'] ?? 'lab_result',
                    ]);
                }

                PatientJourneyEvent::create([
                    'id'                 => \Illuminate\Support\Str::uuid(),
                    'tenant_id'          => app('current_tenant_id'),
                    'patient_record_id'  => $patientRecord->id,
                    'from_department_id' => $user->department_id,
                    'to_department_id'   => $user->department_id,
                    'event_type'         => 'note_added',
                    'performed_by'       => $user->id,
                    'notes'              => "Lab result recorded: {$item['test_name']}",
                ]);

                AuditLog::create([
                    'tenant_id'     => app('current_tenant_id'),
                    'actor_id'      => $user->id,
                    'actor_role'    => $user->role,
                    'action'        => 'lab_result.recorded',
                    'resource_type' => 'LabResult',
                    'resource_id'   => $labResult->id,
                    'description'   => "Lab result recorded by {$user->name}: {$item['test_name']}",
                    'ip_address'    => $request->ip(),
                ]);

                $savedResults[] = $labResult;
            }

            // Route back to originating doctor's department.
            // We look for the OLDEST 'routed' event that brought the patient TO the lab
            // from a non-accounts department (i.e. the original doctor routing).
            $labDeptId = $patientRecord->current_department_id;

            // Find the accounts department to exclude it
            $accountsDept = \App\Models\Department::where('tenant_id', app('current_tenant_id'))
                ->where('type', 'accounts')
                ->first()
                ?? \App\Models\Department::where('tenant_id', app('current_tenant_id'))
                    ->where('name', 'like', '%account%')
                    ->first();

            $routeToLabEvent = PatientJourneyEvent::where('patient_record_id', $patientRecord->id)
                ->where('to_department_id', $labDeptId)
                ->where('event_type', 'routed')
                ->whereNotNull('from_department_id')
                ->when($accountsDept, fn($q) => $q->where('from_department_id', '!=', $accountsDept->id))
                ->orderBy('created_at', 'asc') // oldest first = original doctor routing
                ->first();

            $returnDepartmentId = $routeToLabEvent?->from_department_id;

            if ($returnDepartmentId) {
                try {
                    $patientRecord = $this->workflowService->routeToSpecificDepartment(
                        $patientRecord,
                        $returnDepartmentId,
                        $user,
                        ['notes' => 'Lab results completed. Returning to originating department.']
                    );
                    $this->notifyDepartmentStaff($returnDepartmentId, $patientRecord, $savedResults[0]);
                    // Invalidate queue cache for both lab dept (patient left) and doctor dept (patient arrived)
                    $this->invalidateDepartmentQueueCache($labDeptId);
                    $this->invalidateDepartmentQueueCache($returnDepartmentId);
                } catch (\Exception $routingError) {
                    \Log::error('Failed to route patient after lab results', [
                        'patient_record_id' => $patientRecord->id,
                        'error' => $routingError->getMessage(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => [
                    'lab_results'    => LabResult::with(['technician'])
                        ->whereIn('id', array_map(fn($r) => $r->id, $savedResults))
                        ->get(),
                    'patient_record' => $patientRecord->fresh(['currentDepartment']),
                ],
                'message' => 'Lab results recorded successfully',
                'warning' => $hasInvestigationNotes ? null : 'No investigation request notes found for this patient record.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to record lab results', ['patient_record_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'LAB_RESULT_ERROR', 'message' => 'Failed to record lab results', 'details' => config('app.debug') ? $e->getMessage() : null],
            ], 500);
        }
    }

    /**
     * Invalidate the department queue cache so the next fetch is fresh.
     */
    protected function invalidateDepartmentQueueCache(?string $departmentId): void
    {
        if ($departmentId) {
            $cacheKey = sprintf('tenant:%s:dept_queue:%s', app('current_tenant_id'), $departmentId);
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }
    }

    /**
     * Notify staff in a department about updated lab results.
     */
    protected function notifyDepartmentStaff(string $departmentId, PatientRecord $patientRecord, LabResult $labResult): void
    {
        try {
            $staffMembers = \App\Models\User::where('department_id', $departmentId)
                ->where('is_active', true)
                ->get();

            foreach ($staffMembers as $staff) {
                Notification::create([
                    'recipient_id' => $staff->id,
                    'type'         => 'lab_results_ready',
                    'channel'      => 'in_app',
                    'payload'      => [
                        'patient_record_id' => $patientRecord->id,
                        'patient_id'        => $patientRecord->patient_id,
                        'lab_result_id'     => $labResult->id,
                        'test_name'         => $labResult->test_name,
                        'message'           => "Lab results ready: {$labResult->test_name}",
                    ],
                    'status'   => 'pending',
                    'attempts' => 0,
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to notify department staff about lab results', [
                'department_id'     => $departmentId,
                'patient_record_id' => $patientRecord->id,
                'error'             => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get lab results for a patient record.
     * GET /api/v1/patient-records/{id}/lab-results
     */
    public function getLabResults(string $id, Request $request): JsonResponse
    {
        $patientRecord = PatientRecord::find($id);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Patient record not found',
                ],
            ], 404);
        }

        $labResults = LabResult::where('patient_record_id', $patientRecord->id)
            ->with(['technician'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $labResults,
        ]);
    }
}

