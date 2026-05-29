<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\AttachDocumentRequest;
use App\Http\Requests\Patient\RegisterPatientRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PatientController extends Controller
{
    /**
     * Get patients registered today (last 24 hours) visible to front desk.
     * GET /api/v1/patients/registered-today
     */
    public function registeredToday(Request $request): JsonResponse
    {
        // Use last 24 hours to avoid timezone issues with whereDate()
        $since = now()->subHours(24);

        $patients = Patient::with(['records' => function ($q) {
            $q->with(['currentDepartment', 'assignedDoctor.department'])->latest();
        }])
        ->where('created_at', '>=', $since)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($patient) {
            $latestRecord = $patient->records->first();
            return [
                'id'                           => $patient->id,
                'patient_number'               => $patient->patient_number,
                'full_name'                    => $patient->full_name,
                'gender'                       => $patient->gender,
                'phone'                        => $patient->phone,
                'chief_complaint'              => $patient->chief_complaint,
                'photo_object_key'             => $patient->photo_object_key,
                'created_at'                   => $patient->created_at,
                'record_status'                => $latestRecord?->status ?? 'unknown',
                'current_department'           => $latestRecord?->currentDepartment?->name ?? 'N/A',
                'patient_record_id'            => $latestRecord?->id,
                'visit_type'                   => $latestRecord?->visit_type,
                'assigned_doctor_id'           => $latestRecord?->assigned_doctor_id,
                'assigned_doctor_name'         => $latestRecord?->assignedDoctor?->name,
                'assigned_doctor_department_id' => $latestRecord?->assignedDoctor?->department_id,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $patients,
        ]);
    }

    /**
     * Register a new patient.
     * POST /api/v1/patients
     */
    public function register(RegisterPatientRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Generate unique patient number (format: P-YYYYMMDD-XXXX)
            $patientNumber = $this->generatePatientNumber();

            // Create patient record
            // Handle photo upload — prefer direct file upload over legacy object key
            $photoObjectKey = null;
            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                try {
                    $tenantId = app('current_tenant_id');
                    $ext = $request->file('photo')->getClientOriginalExtension() ?: 'jpg';
                    $basename = uniqid('photo_', true) . '.' . $ext;
                    // Store at {tenantId}/{basename} within the patient_photos disk
                    // Disk root = storage/app/patient-photos/
                    Storage::disk('patient_photos')->putFileAs(
                        $tenantId,
                        $request->file('photo'),
                        $basename
                    );
                    $photoObjectKey = $tenantId . '/' . $basename;
                } catch (\Exception $photoEx) {
                    // Photo save failed — log and continue without photo
                    \Illuminate\Support\Facades\Log::warning('Patient photo save failed', [
                        'error' => $photoEx->getMessage(),
                    ]);
                }
            } elseif ($request->photo_object_key) {
                // Legacy: object key from a prior S3 presigned upload
                $photoObjectKey = $request->photo_object_key;
            }

            $patient = Patient::create([
                'tenant_id'        => app('current_tenant_id'),
                'patient_number'   => $patientNumber,
                'full_name'        => $request->full_name,
                'date_of_birth'    => $request->date_of_birth,
                'gender'           => $request->gender,
                'phone'            => $request->phone,
                'address'          => $request->address,
                'next_of_kin_name' => $request->next_of_kin_name,
                'next_of_kin_phone' => $request->next_of_kin_phone,
                'chief_complaint'  => $request->chief_complaint,
                'hmo_provider_name' => $request->hmo_provider_name,
                'hmo_policy_number' => $request->hmo_policy_number,
                'photo_object_key' => $photoObjectKey,
                'created_by'       => Auth::id(),
            ]);

            // Get Accounts department
            $accountsDepartment = Department::where('type', 'accounts')->first();

            if (!$accountsDepartment) {
                throw new \Exception('Accounts department not found. Please configure the Accounts department first.');
            }

            // Get default workflow template
            $defaultTemplate = \App\Models\WorkflowTemplate::where('is_default', true)->first();
            if (!$defaultTemplate) {
                $defaultTemplate = \App\Models\WorkflowTemplate::first();
            }

            // Find the step order for Accounts department in the workflow template
            $accountsStepOrder = null;
            if ($defaultTemplate) {
                $accountsStep = \App\Models\WorkflowTemplateStep::where('template_id', $defaultTemplate->id)
                    ->where('department_id', $accountsDepartment->id)
                    ->first();
                $accountsStepOrder = $accountsStep?->step_order;
            }

            // Create patient_record with status pending_payment
            $patientRecord = PatientRecord::create([
                'tenant_id'             => app('current_tenant_id'),
                'patient_id'            => $patient->id,
                'workflow_template_id'  => $defaultTemplate?->id,
                'status'                => 'pending_payment',
                'current_department_id' => $accountsDepartment->id,
                'current_step_order'    => $accountsStepOrder,
                'registered_by'         => Auth::id(),
                'visit_type'            => $request->input('visit_type'),
                'assigned_doctor_id'    => $request->input('assigned_doctor_id'),
            ]);

            // Create journey event for registration
            PatientJourneyEvent::create([
                'tenant_id'         => app('current_tenant_id'),
                'patient_record_id' => $patientRecord->id,
                'to_department_id'  => $accountsDepartment->id,
                'event_type'        => 'registered',
                'performed_by'      => Auth::id(),
                'notes'             => 'Patient registered and routed to Accounts department for payment',
                'metadata'          => array_filter([
                    'visit_type'         => $request->input('visit_type'),
                    'assigned_doctor_id' => $request->input('assigned_doctor_id'),
                ]),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'patient' => $patient,
                    'patient_record' => $patientRecord->load(['currentDepartment', 'registrar']),
                ],
                'message' => 'Patient registered successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REGISTRATION_ERROR',
                    'message' => 'Failed to register patient',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Find a patient by ID and verify it belongs to the current tenant.
     * Returns null if the patient does not exist OR belongs to a different tenant,
     * preventing cross-tenant existence disclosure.
     */
    private function findPatientForTenant(string $id): ?Patient
    {
        $patient = Patient::find($id);
        if (!$patient || $patient->tenant_id !== app('current_tenant_id')) {
            return null;
        }
        return $patient;
    }

    /**
     * Get patient record with decrypted PII.
     * GET /api/v1/patients/{id}
     */
    public function show(string $id): JsonResponse
    {
        $patient = $this->findPatientForTenant($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient not found',
                ],
            ], 404);
        }

        // Load relations after tenant check
        $patient->load(['records.currentDepartment', 'records.registrar']);

        // Decrypt PII fields (handled by model accessors)
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $patient->id,
                'patient_number' => $patient->patient_number,
                'full_name' => $patient->full_name,
                'date_of_birth' => $patient->date_of_birth,
                'gender' => $patient->gender,
                'phone' => $patient->phone,
                'address' => $patient->address,
                'next_of_kin_name' => $patient->next_of_kin_name,
                'next_of_kin_phone' => $patient->next_of_kin_phone,
                'chief_complaint' => $patient->chief_complaint,
                'hmo_provider_name' => $patient->hmo_provider_name,
                'hmo_policy_number' => $patient->hmo_policy_number,
                'photo_object_key' => $patient->photo_object_key,
                'is_anonymised' => $patient->is_anonymised,
                'created_at' => $patient->created_at,
                'updated_at' => $patient->updated_at,
                'records' => $patient->records,
            ],
        ]);
    }

    /**
     * Get full journey history for a patient.
     * GET /api/v1/patients/{id}/journey
     */
    public function journey(string $id): JsonResponse
    {
        $patient = $this->findPatientForTenant($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient not found',
                ],
            ], 404);
        }

        // Get all patient records with journey events
        $records = PatientRecord::where('patient_id', $id)
            ->with([
                'journeyEvents' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                },
                'journeyEvents.fromDepartment',
                'journeyEvents.toDepartment',
                'journeyEvents.performer',
            ])
            ->get();

        // Flatten all journey events across all records and sort chronologically
        $allJourneyEvents = $records->flatMap(function ($record) {
            return $record->journeyEvents;
        })->sortBy('created_at')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'patient_id' => $patient->id,
                'patient_number' => $patient->patient_number,
                'journey_events' => $allJourneyEvents,
            ],
        ]);
    }

    /**
     * Attach document to patient record.
     * POST /api/v1/patients/{id}/documents
     */
    public function attachDocument(string $id, AttachDocumentRequest $request): JsonResponse
    {
        $patient = $this->findPatientForTenant($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient not found',
                ],
            ], 404);
        }

        try {
            DB::beginTransaction();

            $document = PatientDocument::create([
                'id'                => \Illuminate\Support\Str::uuid(),
                'tenant_id'         => app('current_tenant_id'),
                'patient_id'        => PatientRecord::find($request->patient_record_id)?->patient_id,
                'uploader_id'       => Auth::id(),
                'document_type'     => $request->document_type,
                'object_key'        => $request->object_key,
                'original_filename' => $request->original_filename,
            ]);

            PatientJourneyEvent::create([
                'id'                => \Illuminate\Support\Str::uuid(),
                'tenant_id'         => app('current_tenant_id'),
                'patient_record_id' => $request->patient_record_id,
                'event_type'        => 'note_added',
                'performed_by'      => Auth::id(),
                'notes'             => "Document attached: {$request->original_filename}",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $document->load(['uploader', 'patientRecord']),
                'message' => 'Document attached successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOCUMENT_ATTACH_ERROR',
                    'message' => 'Failed to attach document',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Generate 15-minute signed R2 URL for document download.
     * GET /api/v1/patients/{id}/documents/{docId}/url
     */
    public function getDocumentUrl(string $id, string $docId): JsonResponse
    {
        $patient = $this->findPatientForTenant($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient not found',
                ],
            ], 404);
        }

        $document = PatientDocument::find($docId);

        if (!$document) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Document not found',
                ],
            ], 404);
        }

        // Verify document belongs to patient
        $patientRecord = PatientRecord::where('id', $document->patient_record_id)
            ->where('patient_id', $id)
            ->first();

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Document does not belong to this patient',
                ],
            ], 403);
        }

        try {
            // Generate signed URL with 15-minute expiry
            $url = Storage::disk('s3')->temporaryUrl(
                $document->object_key,
                now()->addMinutes(15)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                    'document' => [
                        'id' => $document->id,
                        'filename' => $document->original_filename,
                        'type' => $document->document_type,
                        'size' => $document->file_size_bytes,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'URL_GENERATION_ERROR',
                    'message' => 'Failed to generate signed URL',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Generate pre-signed R2 PUT URL for direct upload.
     * GET /api/v1/upload/presigned-url
     */
    public function getPresignedUploadUrl(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => 'required|string|max:255',
            'content_type' => 'required|string',
            'file_size' => 'required|integer|min:1|max:10485760', // 10 MB max
        ]);

        try {
            // Generate unique object key
            $extension = pathinfo($request->filename, PATHINFO_EXTENSION);
            $objectKey = 'uploads/' . uniqid() . '_' . time() . '.' . $extension;

            // Generate pre-signed PUT URL with 15-minute expiry
            $url = Storage::disk('s3')->temporaryUploadUrl(
                $objectKey,
                now()->addMinutes(15),
                [
                    'ContentType' => $request->content_type,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'upload_url' => $url,
                    'object_key' => $objectKey,
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                    'method' => 'PUT',
                    'headers' => [
                        'Content-Type' => $request->content_type,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRESIGNED_URL_ERROR',
                    'message' => 'Failed to generate pre-signed upload URL',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Anonymise a patient's PII fields.
     * POST /api/v1/patients/{id}/anonymise
     * Requires hospital_admin role.
     */
    public function anonymise(string $id, Request $request): JsonResponse
    {
        // Only hospital_admin may anonymise
        $user = Auth::user();
        if (!$user || $user->role !== 'hospital_admin') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Only hospital administrators can anonymise patient records.',
                ],
            ], 403);
        }

        $patient = $this->findPatientForTenant($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Patient not found',
                ],
            ], 404);
        }

        if ($patient->is_anonymised) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALREADY_ANONYMISED',
                    'message' => 'Patient record has already been anonymised.',
                ],
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Replace PII with placeholders (setters will encrypt the placeholder values)
            $patient->full_name    = '[ANONYMISED]';
            $patient->date_of_birth = '1900-01-01';
            $patient->phone        = '[ANONYMISED]';
            $patient->address      = '[ANONYMISED]';
            $patient->is_anonymised = true;
            $patient->save();

            AuditLog::create([
                'tenant_id'     => app('current_tenant_id'),
                'actor_id'      => $user->id,
                'actor_role'    => $user->role,
                'action'        => 'patient.anonymised',
                'resource_type' => 'Patient',
                'resource_id'   => $patient->id,
                'description'   => "Patient record {$patient->patient_number} anonymised by {$user->name}",
                'ip_address'    => $request->ip(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'id'             => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'full_name'      => $patient->full_name,
                    'date_of_birth'  => $patient->date_of_birth,
                    'gender'         => $patient->gender,
                    'phone'          => $patient->phone,
                    'address'        => $patient->address,
                    'is_anonymised'  => $patient->is_anonymised,
                    'updated_at'     => $patient->updated_at,
                ],
                'message' => 'Patient record anonymised successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ANONYMISATION_ERROR',
                    'message' => 'Failed to anonymise patient record.',
                    'details' => config('app.debug') ? $e->getMessage() : null,
                ],
            ], 500);
        }
    }

    /**
     * Search patients by name or patient number.
     * GET /api/v1/patients/search?q=...
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $query = $request->input('q');

        $patients = Patient::where(function ($q) use ($query) {
            $q->where('full_name', 'like', "%{$query}%")
              ->orWhere('patient_number', 'like', "%{$query}%")
              ->orWhere('phone', 'like', "%{$query}%");
        })
        ->where('is_anonymised', false)
        ->withCount('records')
        ->with(['records' => function ($q) {
            $q->latest()->select('id', 'patient_id', 'created_at');
        }])
        ->limit(20)
        ->get()
        ->map(function ($patient) {
            return [
                'id'             => $patient->id,
                'patient_number' => $patient->patient_number,
                'full_name'      => $patient->full_name,
                'date_of_birth'  => $patient->date_of_birth instanceof \Carbon\Carbon
                    ? $patient->date_of_birth->toDateString()
                    : $patient->date_of_birth,
                'gender'         => $patient->gender,
                'phone'          => $patient->phone,
                'visit_count'    => $patient->records_count,
                'last_visit'     => $patient->records->first()?->created_at?->toDateString(),
                'photo_object_key' => $patient->photo_object_key,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $patients,
        ]);
    }

    /**
     * Create a new visit (patient record) for an existing patient.
     * POST /api/v1/patients/{id}/new-visit
     */
    public function newVisit(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'chief_complaint'     => 'required|string|max:500',
            'workflow_template_id' => 'nullable|uuid',
            'visit_type'          => 'nullable|string|max:100',
            'assigned_doctor_id'  => 'nullable|uuid',
        ]);

        $patient = $this->findPatientForTenant($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Patient not found'],
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Update chief complaint for this visit
            $patient->update(['chief_complaint' => $request->input('chief_complaint')]);

            // Get accounts department
            $accountsDepartment = Department::where('type', 'accounts')->first();
            if (!$accountsDepartment) {
                throw new \Exception('Accounts department not configured.');
            }

            // Determine workflow template
            $workflowTemplateId = $request->input('workflow_template_id');
            if (!$workflowTemplateId) {
                $defaultTemplate = \App\Models\WorkflowTemplate::where('is_default', true)->first();
                $workflowTemplateId = $defaultTemplate?->id;
            }

            // Create new patient record (visit)
            $patientRecord = PatientRecord::create([
                'id'                    => \Illuminate\Support\Str::uuid(),
                'tenant_id'             => app('current_tenant_id'),
                'patient_id'            => $patient->id,
                'workflow_template_id'  => $workflowTemplateId,
                'status'                => 'pending_payment',
                'current_department_id' => $accountsDepartment->id,
                'registered_by'         => request()->user()?->id ?? auth('web')->id(),
                'visit_type'            => $request->input('visit_type'),
                'assigned_doctor_id'    => $request->input('assigned_doctor_id'),
            ]);

            PatientJourneyEvent::create([
                'id'                => \Illuminate\Support\Str::uuid(),
                'tenant_id'         => app('current_tenant_id'),
                'patient_record_id' => $patientRecord->id,
                'to_department_id'  => $accountsDepartment->id,
                'event_type'        => 'registered',
                'performed_by'      => request()->user()?->id ?? auth('web')->id(),
                'notes'             => 'Returning patient — new visit created.',
                'metadata'          => array_filter([
                    'visit_type'         => $request->input('visit_type'),
                    'assigned_doctor_id' => $request->input('assigned_doctor_id'),
                ]),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => [
                    'patient'        => $patient,
                    'patient_record' => $patientRecord->load('currentDepartment'),
                ],
                'message' => 'New visit created. Patient routed to Accounts.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'VISIT_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Generate a 15-minute signed URL for the patient's photograph.
     * GET /api/v1/patients/{id}/photo/url
     */
    /**
     * Serve a patient's photograph directly from local storage.
     * GET /api/v1/patients/{id}/photo
     *
     * Returns the image file as a binary response — no expiring signed URLs.
     * Both mobile and web can cache this URL permanently.
     */
    public function getPhoto(string $id)
    {
        $patient = $this->findPatientForTenant($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found'],
            ], 404);
        }

        if (empty($patient->photo_object_key)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NO_PHOTO', 'message' => 'No photograph on file for this patient'],
            ], 404);
        }

        // Try local disk first (new flow)
        if (Storage::disk('patient_photos')->exists($patient->photo_object_key)) {
            $path = Storage::disk('patient_photos')->path($patient->photo_object_key);
            $mime = mime_content_type($path) ?: 'image/jpeg';
            return response()->file($path, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'private, max-age=86400', // 24-hour browser cache
            ]);
        }

        // Fallback: legacy S3/R2 object key — generate a short-lived signed URL and redirect
        try {
            $url = Storage::disk('s3')->temporaryUrl(
                $patient->photo_object_key,
                now()->addMinutes(15)
            );
            return redirect($url);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PHOTO_NOT_FOUND', 'message' => 'Photo could not be retrieved'],
            ], 404);
        }
    }

    /**
     * @deprecated Use getPhoto() instead. Kept for backward compatibility.
     * GET /api/v1/patients/{id}/photo/url
     */
    public function getPhotoUrl(string $id): JsonResponse
    {
        $patient = $this->findPatientForTenant($id);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found'],
            ], 404);
        }

        if (empty($patient->photo_object_key)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NO_PHOTO', 'message' => 'No photograph on file for this patient'],
            ], 404);
        }

        // If stored locally, return the permanent API URL
        if (Storage::disk('patient_photos')->exists($patient->photo_object_key)) {
            $permanentUrl = url("/api/v1/patients/{$id}/photo");
            return response()->json([
                'success' => true,
                'data' => [
                    'url'        => $permanentUrl,
                    'expires_at' => null, // permanent — no expiry
                ],
            ]);
        }

        // Legacy S3 fallback
        try {
            $url = Storage::disk('s3')->temporaryUrl(
                $patient->photo_object_key,
                now()->addMinutes(60)
            );
            return response()->json([
                'success' => true,
                'data' => [
                    'url'        => $url,
                    'expires_at' => now()->addMinutes(60)->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'URL_GENERATION_ERROR', 'message' => 'Failed to generate photo URL'],
            ], 500);
        }
    }

    /**
     * Generate unique patient number in format P-YYYYMMDD-XXXX.
     */
    private function generatePatientNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "P-{$date}-";

        // Get the last patient number for today
        $lastPatient = Patient::where('patient_number', 'like', $prefix . '%')
            ->orderBy('patient_number', 'desc')
            ->first();

        if ($lastPatient) {
            // Extract the sequence number and increment
            $lastSequence = (int) substr($lastPatient->patient_number, -4);
            $newSequence = $lastSequence + 1;
        } else {
            // First patient of the day
            $newSequence = 1;
        }

        // Format with leading zeros (4 digits)
        return $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }
}
