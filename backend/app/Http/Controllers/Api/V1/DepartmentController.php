<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    protected WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Get departments by type — accessible to any authenticated staff.
     * Used by doctors to find pharmacy/lab/ward departments for routing.
     *
     * GET /api/v1/departments/by-type/{type}
     */
    public function getByType(string $type, Request $request): JsonResponse
    {
        $departments = \App\Models\Department::where('type', $type)
            ->select('id', 'name', 'type')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $departments,
        ]);
    }

    /**
     * Get department queue - patient records assigned to authenticated user's department.
     * Excludes pending_payment records. Cached with 30-second TTL.
     *
     * GET /api/v1/department/queue
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getQueue(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            if (!$user->department_id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NO_DEPARTMENT',
                        'message' => 'User is not assigned to a department',
                    ],
                ], 400);
            }

            // Cache key includes tenant and department for isolation
            // Format: tenant:{tenant_id}:dept_queue:{dept_id}
            $cacheKey = sprintf(
                'tenant:%s:dept_queue:%s',
                app('current_tenant_id'),
                $user->department_id
            );

            $patientRecords = Cache::remember($cacheKey, 5, function () use ($user, $request) {
                return PatientRecord::with([
                    'patient',
                    'currentDepartment',
                    'workflowTemplate',
                    'invoices',
                    'assignedDoctor.department',
                ])
                    ->where('current_department_id', $user->department_id)
                    ->where('status', '!=', 'pending_payment')
                    ->orderBy('created_at', 'asc')
                    ->paginate(20);
            });

            return response()->json([
                'success' => true,
                'data' => collect($patientRecords->items())->map(function ($record) {
                    $data = $record->toArray();
                    // Include lab/pharmacy bill items from journey events so lab/pharmacy
                    // can distinguish between consultation-paid and test-paid patients
                    $labBillingEvent = \App\Models\PatientJourneyEvent::where('patient_record_id', $record->id)
                        ->whereNotNull('metadata->lab_bill_items')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    $pharmBillingEvent = \App\Models\PatientJourneyEvent::where('patient_record_id', $record->id)
                        ->whereNotNull('metadata->pharmacy_bill_items')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    $data['lab_bill_items']      = $labBillingEvent?->metadata['lab_bill_items'] ?? null;
                    $data['pharmacy_bill_items'] = $pharmBillingEvent?->metadata['pharmacy_bill_items'] ?? null;

                    // Include investigation notes (doctor's test requests) for lab tech
                    $investigationEvent = \App\Models\PatientJourneyEvent::where('patient_record_id', $record->id)
                        ->whereNotNull('metadata->investigation_notes')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    $data['investigation_notes'] = $investigationEvent?->metadata['investigation_notes'] ?? null;

                    // Include prescription notes for pharmacist
                    $prescriptionEvent = \App\Models\PatientJourneyEvent::where('patient_record_id', $record->id)
                        ->whereNotNull('metadata->prescription_notes')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    $data['prescription_notes'] = $prescriptionEvent?->metadata['prescription_notes'] ?? null;

                    // Include complaint history and examination findings for doctor
                    $complaintEvent = \App\Models\PatientJourneyEvent::where('patient_record_id', $record->id)
                        ->whereNotNull('metadata->complaint_history')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    $data['complaint_history'] = $complaintEvent?->metadata['complaint_history'] ?? null;

                    $examinationEvent = \App\Models\PatientJourneyEvent::where('patient_record_id', $record->id)
                        ->whereNotNull('metadata->examination_findings')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    $data['examination_findings'] = $examinationEvent?->metadata['examination_findings'] ?? null;

                    // Include latest lab results summary for doctor
                    $latestLabResults = \App\Models\LabResult::where('patient_record_id', $record->id)
                        ->orderBy('created_at', 'desc')
                        ->get(['test_name', 'result', 'reference_range', 'created_at'])
                        ->map(fn($r) => [
                            'test_name'       => $r->test_name,
                            'result'          => $r->result,
                            'reference_range' => $r->reference_range,
                            'created_at'      => $r->created_at->format('Y-m-d H:i'),
                        ])
                        ->toArray();
                    $data['latest_lab_results'] = !empty($latestLabResults) ? $latestLabResults : null;

                    // Assigned doctor info for nurse/triage routing
                    $data['visit_type']                    = $record->visit_type;
                    $data['assigned_doctor_id']            = $record->assigned_doctor_id;
                    $data['assigned_doctor_name']          = $record->assignedDoctor?->name;
                    $data['assigned_doctor_department_id'] = $record->assignedDoctor?->department_id;

                    return $data;
                }),
                'meta' => [
                    'pagination' => [
                        'current_page' => $patientRecords->currentPage(),
                        'per_page'     => $patientRecords->perPage(),
                        'total'        => $patientRecords->total(),
                        'last_page'    => $patientRecords->lastPage(),
                    ],
                    'department_id' => $user->department_id,
                    'cached' => true,
                    'cache_ttl' => 5,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to retrieve department queue',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * Get journey history for a specific patient record.
     * GET /api/v1/patient-records/{id}/journey
     *
     * @param Request $request
     * @param string $id Patient record ID
     * @return JsonResponse
     */
    public function getJourneyHistory(Request $request, string $id): JsonResponse
    {
        try {
            $patientRecord = PatientRecord::with([
                'journeyEvents' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                },
                'journeyEvents.fromDepartment',
                'journeyEvents.toDepartment',
                'journeyEvents.performer',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $patientRecord->journeyEvents,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient record not found',
                ],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to retrieve journey history',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * Get prescription notes for a patient record.
     * GET /api/v1/patient-records/{id}/prescription
     *
     * @param string $id Patient record ID
     * @return JsonResponse
     */
    public function getPrescription(string $id): JsonResponse
    {
        try {
            $patientRecord = PatientRecord::with('patient')->findOrFail($id);

            // Find the most recent journey event with prescription notes
            $prescriptionEvent = PatientJourneyEvent::where('patient_record_id', $id)
                ->whereNotNull('metadata->prescription_notes')
                ->orderBy('created_at', 'desc')
                ->with('performer')
                ->first();

            if (!$prescriptionEvent) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No prescription found for this patient record',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'patient_record_id' => $patientRecord->id,
                    'patient_name' => $patientRecord->patient->full_name ?? null,
                    'patient_number' => $patientRecord->patient->patient_number ?? null,
                    'prescription_notes' => $prescriptionEvent->metadata['prescription_notes'] ?? null,
                    'prescribed_by' => $prescriptionEvent->performer->name ?? null,
                    'prescribed_at' => $prescriptionEvent->created_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient record not found',
                ],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to retrieve prescription',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * Get investigation notes (lab test types) for a patient record.
     * GET /api/v1/patient-records/{id}/investigation
     *
     * @param string $id Patient record ID
     * @return JsonResponse
     */
    public function getInvestigation(string $id): JsonResponse
    {
        try {
            \Log::info('getInvestigation called', ['patient_record_id' => $id]);
            
            $patientRecord = PatientRecord::with('patient')->findOrFail($id);

            \Log::info('Patient record found', [
                'patient_record_id' => $patientRecord->id,
                'status' => $patientRecord->status,
                'current_department_id' => $patientRecord->current_department_id,
            ]);

            // Find the most recent journey event with investigation notes
            $investigationEvent = PatientJourneyEvent::where('patient_record_id', $id)
                ->whereNotNull('metadata->investigation_notes')
                ->orderBy('created_at', 'desc')
                ->with('performer')
                ->first();

            if (!$investigationEvent) {
                \Log::info('No investigation notes found', ['patient_record_id' => $id]);
                
                // Log all journey events for debugging
                $allEvents = PatientJourneyEvent::where('patient_record_id', $id)
                    ->whereNotNull('metadata')
                    ->orderBy('created_at', 'desc')
                    ->get(['id', 'event_type', 'metadata', 'created_at']);
                
                \Log::info('All journey events with metadata', [
                    'patient_record_id' => $id,
                    'events_count' => $allEvents->count(),
                    'events' => $allEvents->map(function ($e) {
                        return [
                            'id' => $e->id,
                            'type' => $e->event_type,
                            'metadata_keys' => array_keys($e->metadata ?? []),
                            'created_at' => $e->created_at,
                        ];
                    }),
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No investigation notes found for this patient record',
                ]);
            }

            \Log::info('Investigation notes found', [
                'patient_record_id' => $id,
                'event_id' => $investigationEvent->id,
                'investigation_notes' => $investigationEvent->metadata['investigation_notes'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'patient_record_id' => $patientRecord->id,
                    'patient_name' => $patientRecord->patient->full_name ?? null,
                    'patient_number' => $patientRecord->patient->patient_number ?? null,
                    'investigation_notes' => $investigationEvent->metadata['investigation_notes'] ?? null,
                    'requested_by' => $investigationEvent->performer->name ?? null,
                    'requested_at' => $investigationEvent->created_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::warning('Patient record not found', ['patient_record_id' => $id]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient record not found',
                ],
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve investigation notes', [
                'patient_record_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to retrieve investigation notes',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * Add note to patient record journey events.
     * Validates record is assigned to user's department.
     *
     * POST /api/v1/patient-records/{id}/notes
     *
     * @param string $id
     * @param Request $request
     * @return JsonResponse
     */
    public function addNote(string $id, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'notes'                => 'required|string|max:5000',
                'complaint_history'    => 'nullable|string|max:5000',
                'examination_findings' => 'nullable|string|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'details' => $validator->errors(),
                    ],
                ], 422);
            }

            /** @var User $user */
            $user = $request->user();

            $patientRecord = PatientRecord::findOrFail($id);

            // Validate record is assigned to user's department
            if ($patientRecord->current_department_id !== $user->department_id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Patient record is not assigned to your department',
                    ],
                ], 403);
            }

            // Prevent editing in-transit records
            if ($patientRecord->status === 'in_transit') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CONFLICT',
                        'message' => 'Cannot edit patient record while in transit',
                    ],
                ], 409);
            }

            DB::transaction(function () use ($patientRecord, $user, $request) {
                $metadata = array_filter([
                    'complaint_history'    => $request->input('complaint_history'),
                    'examination_findings' => $request->input('examination_findings'),
                ]);

                PatientJourneyEvent::create([
                    'tenant_id'         => app('current_tenant_id'),
                    'patient_record_id' => $patientRecord->id,
                    'from_department_id' => $user->department_id,
                    'to_department_id'  => $user->department_id,
                    'event_type'        => 'note_added',
                    'performed_by'      => $user->id,
                    'notes'             => $request->input('notes'),
                    'metadata'          => !empty($metadata) ? $metadata : null,
                ]);

                AuditLog::create([
                    'tenant_id'     => app('current_tenant_id'),
                    'actor_id'      => $user->id,
                    'actor_role'    => $user->role,
                    'action'        => 'patient_record.note_added',
                    'resource_type' => 'PatientRecord',
                    'resource_id'   => $patientRecord->id,
                    'description'   => "Note added to patient record {$patientRecord->id} by {$user->name}",
                    'ip_address'    => $request->ip(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully',
                'data' => $patientRecord->fresh()->load('journeyEvents'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient record not found',
                ],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to add note',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * Record vitals for a patient (nurse/triage).
     * POST /api/v1/patient-records/{id}/vitals
     */
    public function recordVitals(string $id, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'blood_pressure'    => 'nullable|string|max:20',
                'temperature'       => 'nullable|numeric|between:30,45',
                'pulse_rate'        => 'nullable|integer|between:20,300',
                'respiratory_rate'  => 'nullable|integer|between:5,60',
                'oxygen_saturation' => 'nullable|integer|between:50,100',
                'weight'            => 'nullable|numeric|between:0.5,500',
                'height'            => 'nullable|numeric|between:20,300',
                'blood_glucose'     => 'nullable|integer|between:10,1000',
                'notes'             => 'nullable|string|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid vitals data.', 'details' => $validator->errors()],
                ], 422);
            }

            /** @var \App\Models\User $user */
            $user = $request->user();

            $patientRecord = PatientRecord::findOrFail($id);

            if ($patientRecord->current_department_id !== $user->department_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'Patient record is not assigned to your department'],
                ], 403);
            }

            // Calculate BMI if weight and height provided
            $bmi = null;
            if ($request->filled('weight') && $request->filled('height')) {
                $heightM = $request->height / 100;
                $bmi = round($request->weight / ($heightM * $heightM), 2);
            }

            $vitals = DB::transaction(function () use ($patientRecord, $user, $request, $bmi) {
                $vitals = \App\Models\PatientVitals::create([
                    'tenant_id'         => app('current_tenant_id'),
                    'patient_record_id' => $patientRecord->id,
                    'recorded_by'       => $user->id,
                    'blood_pressure'    => $request->blood_pressure,
                    'temperature'       => $request->temperature,
                    'pulse_rate'        => $request->pulse_rate,
                    'respiratory_rate'  => $request->respiratory_rate,
                    'oxygen_saturation' => $request->oxygen_saturation,
                    'weight'            => $request->weight,
                    'height'            => $request->height,
                    'bmi'               => $bmi,
                    'blood_glucose'     => $request->blood_glucose,
                    'notes'             => $request->notes,
                ]);

                // Add journey event for vitals recording
                PatientJourneyEvent::create([
                    'tenant_id'          => app('current_tenant_id'),
                    'patient_record_id'  => $patientRecord->id,
                    'from_department_id' => $user->department_id,
                    'to_department_id'   => $user->department_id,
                    'event_type'         => 'note_added',
                    'performed_by'       => $user->id,
                    'notes'              => 'Vitals recorded: ' . $this->formatVitalsSummary($request),
                ]);

                AuditLog::create([
                    'tenant_id'     => app('current_tenant_id'),
                    'actor_id'      => $user->id,
                    'actor_role'    => $user->role,
                    'action'        => 'patient_record.vitals_recorded',
                    'resource_type' => 'PatientRecord',
                    'resource_id'   => $patientRecord->id,
                    'description'   => "Vitals recorded for patient record {$patientRecord->id} by {$user->name}",
                    'ip_address'    => $request->ip(),
                ]);

                return $vitals;
            });

            return response()->json([
                'success' => true,
                'message' => 'Vitals recorded successfully',
                'data'    => $vitals,
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient record not found']], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    /**
     * Get vitals for a patient record.
     * GET /api/v1/patient-records/{id}/vitals
     */
    public function getVitals(string $id): JsonResponse
    {
        try {
            $patientRecord = PatientRecord::findOrFail($id);
            $vitals = \App\Models\PatientVitals::where('patient_record_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $vitals]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient record not found']], 404);
        }
    }

    private function formatVitalsSummary(Request $request): string
    {
        $parts = [];
        if ($request->filled('blood_pressure'))    $parts[] = "BP {$request->blood_pressure}";
        if ($request->filled('temperature'))       $parts[] = "Temp {$request->temperature}°C";
        if ($request->filled('pulse_rate'))        $parts[] = "Pulse {$request->pulse_rate}bpm";
        if ($request->filled('oxygen_saturation')) $parts[] = "SpO2 {$request->oxygen_saturation}%";
        if ($request->filled('weight'))            $parts[] = "Wt {$request->weight}kg";
        return empty($parts) ? 'No values entered' : implode(', ', $parts);
    }

    /**
     * Mark patient record step as complete and route to next department.
     *
     * POST /api/v1/patient-records/{id}/complete
     *
     * @param string $id
     * @param Request $request
     * @return JsonResponse
     */
    public function complete(string $id, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'notes'                => 'nullable|string|max:5000',
                'complaint_history'    => 'nullable|string|max:5000',
                'examination_findings' => 'nullable|string|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'details' => $validator->errors(),
                    ],
                ], 422);
            }

            /** @var User $user */
            $user = $request->user();

            $patientRecord = PatientRecord::findOrFail($id);

            // Validate user has a department assigned
            if (!$user->department_id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NO_DEPARTMENT',
                        'message' => 'Your account is not assigned to a department. Contact your administrator.',
                    ],
                ], 403);
            }

            // Validate record is assigned to user's department
            if ($patientRecord->current_department_id !== $user->department_id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Patient record is not assigned to your department',
                    ],
                ], 403);
            }

            // Prevent editing in-transit records
            if ($patientRecord->status === 'in_transit') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CONFLICT',
                        'message' => 'Cannot complete patient record while in transit',
                    ],
                ], 409);
            }

            // Route to next department
            $metadata = [];
            if ($request->has('notes')) {
                $metadata['notes'] = $request->input('notes');
            }
            if ($request->filled('complaint_history')) {
                $metadata['complaint_history'] = $request->input('complaint_history');
            }
            if ($request->filled('examination_findings')) {
                $metadata['examination_findings'] = $request->input('examination_findings');
            }

            $updatedRecord = $this->workflowService->routeToNextDepartment(
                $patientRecord,
                $user,
                $metadata
            );

            // Invalidate queue cache for the old department (patient left) and new department (patient arrived)
            $this->invalidateDepartmentQueueCache($user->department_id);
            if ($updatedRecord->current_department_id) {
                $this->invalidateDepartmentQueueCache($updatedRecord->current_department_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Patient record completed and routed successfully',
                'data' => $updatedRecord->load(['patient', 'currentDepartment', 'journeyEvents']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient record not found',
                ],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to complete patient record',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * Route patient record to specific department (doctor override).
     * If routing to lab or pharmacy, patient goes to Accounts first for payment.
     *
     * POST /api/v1/patient-records/{id}/route
     */
    public function route(string $id, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'department_id'        => 'required|uuid|exists:departments,id',
                'notes'                => 'nullable|string|max:5000',
                'prescription_notes'   => 'nullable|string|max:5000',
                'investigation_notes'  => 'nullable|string|max:5000',
                'complaint_history'    => 'nullable|string|max:5000',
                'examination_findings' => 'nullable|string|max:5000',
                'requires_payment'     => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'The given data was invalid.', 'details' => $validator->errors()],
                ], 422);
            }

            /** @var User $user */
            $user = $request->user();

            // Allow doctors and HODs to route patients
            // Doctors can route patients regardless of current department assignment
            if (!in_array($user->role, ['doctor', 'hod', 'hospital_admin']) && !$user->is_hod) {
                \Log::warning('Route attempt by non-doctor user', [
                    'user_id' => $user->id,
                    'user_role' => $user->role,
                    'is_hod' => $user->is_hod ?? false,
                    'patient_record_id' => $id,
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'Only doctors can route to specific departments'],
                ], 403);
            }

            $patientRecord = PatientRecord::findOrFail($id);

            // Only enforce department check for non-doctors (nurses, etc.)
            // Doctors can route patients from any department they have access to
            if (!in_array($user->role, ['doctor', 'hod', 'hospital_admin']) && !$user->is_hod) {
                if ($patientRecord->current_department_id !== $user->department_id) {
                    return response()->json([
                        'success' => false,
                        'error' => ['code' => 'FORBIDDEN', 'message' => 'Patient record is not assigned to your department'],
                    ], 403);
                }
            }

            if ($patientRecord->status === 'in_transit') {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'CONFLICT', 'message' => 'Cannot route patient record while in transit'],
                ], 409);
            }

            $targetDepartmentId = $request->input('department_id');
            $targetDepartment = \App\Models\Department::find($targetDepartmentId);
            $targetName = strtolower($targetDepartment->name ?? '');

            $metadata = [];
            if ($request->has('notes'))               $metadata['notes']               = $request->input('notes');
            if ($request->has('prescription_notes'))  $metadata['prescription_notes']  = $request->input('prescription_notes');
            if ($request->has('investigation_notes')) $metadata['investigation_notes'] = $request->input('investigation_notes');
            if ($request->filled('complaint_history'))    $metadata['complaint_history']    = $request->input('complaint_history');
            if ($request->filled('examination_findings')) $metadata['examination_findings'] = $request->input('examination_findings');

            // Route directly to the target department (pharmacy/lab will handle their own billing)
            $updatedRecord = $this->workflowService->routeToSpecificDepartment(
                $patientRecord,
                $targetDepartmentId,
                $user,
                $metadata
            );

            $this->invalidateDepartmentQueueCache($user->department_id);
            $this->invalidateDepartmentQueueCache($targetDepartmentId);

            return response()->json([
                'success' => true,
                'message' => 'Patient record routed successfully',
                'data'    => $updatedRecord->load(['patient', 'currentDepartment', 'journeyEvents']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient record or department not found'],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to route patient record',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * Mark patient as admitted.
     *
     * POST /api/v1/patient-records/{id}/admit
     *
     * @param string $id
     * @param Request $request
     * @return JsonResponse
     */
    public function admit(string $id, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string|max:5000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'details' => $validator->errors(),
                    ],
                ], 422);
            }

            /** @var User $user */
            $user = $request->user();

            // Only doctors can admit patients
            if ($user->role !== 'doctor') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Only doctors can admit patients',
                    ],
                ], 403);
            }

            $patientRecord = PatientRecord::findOrFail($id);

            // Validate record is assigned to user's department
            if ($patientRecord->current_department_id !== $user->department_id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Patient record is not assigned to your department',
                    ],
                ], 403);
            }

            // Prevent editing in-transit records
            if ($patientRecord->status === 'in_transit') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CONFLICT',
                        'message' => 'Cannot admit patient record while in transit',
                    ],
                ], 409);
            }

            DB::transaction(function () use ($patientRecord, $user, $request) {
                // Update patient record status
                $patientRecord->update([
                    'status' => 'admitted',
                    'admitted_at' => now(),
                ]);

                PatientJourneyEvent::create([
                    'tenant_id'          => app('current_tenant_id'),
                    'patient_record_id'  => $patientRecord->id,
                    'from_department_id' => $user->department_id,
                    'to_department_id'   => null,
                    'event_type'         => 'admitted',
                    'performed_by'       => $user->id,
                    'notes'              => $request->input('notes'),
                ]);

                AuditLog::create([
                    'tenant_id'     => app('current_tenant_id'),
                    'actor_id'      => $user->id,
                    'actor_role'    => $user->role,
                    'action'        => 'patient_record.admitted',
                    'resource_type' => 'PatientRecord',
                    'resource_id'   => $patientRecord->id,
                    'description'   => "Patient record {$patientRecord->id} marked as admitted by {$user->name}",
                    'ip_address'    => $request->ip(),
                ]);

                // Notify Hospital Admin
                $this->notifyHospitalAdmin($patientRecord, $user);
            });

            return response()->json([
                'success' => true,
                'message' => 'Patient admitted successfully',
                'data' => $patientRecord->fresh()->load(['patient', 'currentDepartment', 'journeyEvents']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient record not found',
                ],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Failed to admit patient',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * Invalidate department queue cache for a given department.
     *
     * @param string|null $departmentId
     * @return void
     */
    protected function invalidateDepartmentQueueCache(?string $departmentId): void
    {
        if ($departmentId) {
            $cacheKey = sprintf('tenant:%s:dept_queue:%s', app('current_tenant_id'), $departmentId);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Notify Hospital Admin about patient admission.
     *
     * @param PatientRecord $patientRecord
     * @param User $doctor
     * @return void
     */
    protected function notifyHospitalAdmin(PatientRecord $patientRecord, User $doctor): void
    {
        try {
            // Get all hospital admins
            $admins = User::where('role', 'hospital_admin')
                ->where('is_active', true)
                ->get();

            foreach ($admins as $admin) {
                Notification::create([
                    'tenant_id'    => app('current_tenant_id'),
                    'recipient_id' => $admin->id,
                    'type'         => 'patient_admitted',
                    'channel'      => 'in_app',
                    'payload'      => [
                        'patient_record_id' => $patientRecord->id,
                        'patient_id'        => $patientRecord->patient_id,
                        'doctor_id'         => $doctor->id,
                        'doctor_name'       => $doctor->name,
                        'message'           => "Patient admitted by Dr. {$doctor->name}",
                    ],
                    'status'   => 'pending',
                    'attempts' => 0,
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the admission operation
            \Log::error('Failed to notify hospital admin about admission', [
                'patient_record_id' => $patientRecord->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
