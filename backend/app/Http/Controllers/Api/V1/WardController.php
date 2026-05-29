<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WardController extends Controller
{
    /**
     * Get all admitted patients for the ward.
     * GET /api/v1/ward/admitted
     */
    public function admittedPatients(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PatientRecord::with(['patient', 'currentDepartment'])
            ->where('status', 'admitted');

        // If user has a department, filter to their ward
        if ($user && $user->department_id) {
            $query->where('current_department_id', $user->department_id);
        }

        $patients = $query->orderBy('admitted_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $patients->map(fn($p) => [
                'id'                   => $p->id,
                'patient_id'           => $p->patient_id,
                'status'               => $p->status,
                'admitted_at'          => $p->admitted_at?->format('Y-m-d H:i:s'),
                'current_department_id' => $p->current_department_id,
                'patient'              => $p->patient ? [
                    'id'             => $p->patient->id,
                    'patient_number' => $p->patient->patient_number,
                    'full_name'      => $p->patient->full_name,
                    'date_of_birth'  => $p->patient->date_of_birth,
                    'gender'         => $p->patient->gender,
                    'phone'          => $p->patient->phone,
                    'chief_complaint' => $p->patient->chief_complaint,
                ] : null,
                'current_department'   => $p->currentDepartment ? [
                    'id'   => $p->currentDepartment->id,
                    'name' => $p->currentDepartment->name,
                ] : null,
            ]),
            'meta' => [
                'total'    => $patients->count(),
                'admitted' => $patients->count(),
            ],
        ]);
    }

    /**
     * Discharge a patient from the ward.
     * POST /api/v1/ward/discharge/{id}
     */
    public function dischargePatient(string $id, Request $request): JsonResponse
    {
        $user = $request->user();

        $patientRecord = PatientRecord::find($id);

        if (!$patientRecord) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Patient record not found.'],
            ], 404);
        }

        if ($patientRecord->status !== 'admitted') {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'INVALID_STATUS', 'message' => 'Patient is not currently admitted.'],
            ], 422);
        }

        $request->validate([
            'discharge_notes' => 'nullable|string|max:2000',
        ]);

        try {
            DB::beginTransaction();

            $fromDeptId = $patientRecord->current_department_id;

            // Find front desk department to route to after discharge
            $frontDesk = \App\Models\Department::where('tenant_id', $patientRecord->tenant_id)
                ->where('type', 'front_desk')
                ->first()
                ?? \App\Models\Department::where('tenant_id', $patientRecord->tenant_id)
                    ->where('name', 'like', '%front%')
                    ->first();

            $patientRecord->update([
                'status'               => 'discharged',
                'discharged_at'        => now(),
                'current_department_id' => $frontDesk?->id,
            ]);

            PatientJourneyEvent::create([
                'id'                 => \Illuminate\Support\Str::uuid(),
                'tenant_id'          => $patientRecord->tenant_id,
                'patient_record_id'  => $patientRecord->id,
                'from_department_id' => $fromDeptId,
                'to_department_id'   => $frontDesk?->id,
                'event_type'         => 'discharged',
                'performed_by'       => $user->id,
                'notes'              => $request->input('discharge_notes') ?? 'Patient discharged from ward.',
            ]);

            AuditLog::create([
                'tenant_id'     => $patientRecord->tenant_id,
                'actor_id'      => $user->id,
                'actor_role'    => $user->role,
                'action'        => 'patient_record.discharged',
                'resource_type' => 'PatientRecord',
                'resource_id'   => $patientRecord->id,
                'description'   => "Patient discharged from ward by {$user->name}",
                'ip_address'    => $request->ip(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Patient discharged successfully.',
                'data'    => $patientRecord->fresh(['patient', 'currentDepartment']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DISCHARGE_FAILED', 'message' => $e->getMessage()],
            ], 500);
        }
    }
}
