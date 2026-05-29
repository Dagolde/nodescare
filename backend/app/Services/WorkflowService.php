<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Notification;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Models\User;
use App\Models\WorkflowTemplateStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkflowService
{
    /**
     * Route patient record to the next department in the workflow sequence.
     *
     * @param PatientRecord $patientRecord
     * @param User $performedBy
     * @param array|null $metadata Additional metadata (e.g., notes, prescription)
     * @return PatientRecord
     * @throws \Exception
     */
    public function routeToNextDepartment(PatientRecord $patientRecord, User $performedBy, ?array $metadata = null): PatientRecord
    {
        return DB::transaction(function () use ($patientRecord, $performedBy, $metadata) {
            // Get the workflow template
            $workflowTemplate = $patientRecord->workflowTemplate;
            
            if (!$workflowTemplate) {
                throw new \Exception('Patient record has no workflow template assigned');
            }

            // Get current step
            $currentStepOrder = $patientRecord->current_step_order ?? 0;
            
            // Find next step
            $nextStep = WorkflowTemplateStep::where('template_id', $workflowTemplate->id)
                ->where('step_order', '>', $currentStepOrder)
                ->orderBy('step_order')
                ->first();

            if (!$nextStep) {
                // No more steps, mark as completed/discharged
                $patientRecord->update([
                    'status' => 'completed',
                    'discharged_at' => now(),
                    'current_department_id' => null,
                    'current_step_order' => null,
                ]);

                // Create journey event
                PatientJourneyEvent::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $patientRecord->tenant_id,
                    'patient_record_id' => $patientRecord->id,
                    'from_department_id' => $patientRecord->current_department_id,
                    'to_department_id' => null,
                    'event_type' => 'discharged',
                    'performed_by' => $performedBy->id,
                    'notes' => 'Patient discharged - workflow completed',
                    'metadata' => $metadata,
                ]);

                // Audit log
                AuditLog::create([
                    'tenant_id' => $patientRecord->tenant_id,
                    'actor_id' => $performedBy->id,
                    'actor_role' => $performedBy->role,
                    'action' => 'patient_record.discharged',
                    'resource_type' => 'PatientRecord',
                    'resource_id' => $patientRecord->id,
                    'description' => "Patient record {$patientRecord->id} discharged by {$performedBy->name}",
                    'ip_address' => request()->ip(),
                    'metadata' => ['patient_record_id' => $patientRecord->id],
                ]);

                return $patientRecord->fresh();
            }

            // Update patient record to next step
            $fromDepartmentId = $patientRecord->current_department_id;
            
            $patientRecord->update([
                'status' => 'in_transit',
                'current_department_id' => $nextStep->department_id,
                'current_step_order' => $nextStep->step_order,
            ]);

            // Create journey event
            PatientJourneyEvent::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'tenant_id' => $patientRecord->tenant_id,
                'patient_record_id' => $patientRecord->id,
                'from_department_id' => $fromDepartmentId,
                'to_department_id' => $nextStep->department_id,
                'event_type' => 'routed',
                'performed_by' => $performedBy->id,
                'notes' => $metadata['notes'] ?? null,
                'metadata' => $metadata,
            ]);

            // Update status back to active after routing
            $patientRecord->update(['status' => 'active']);

            // Audit log
            AuditLog::create([
                'tenant_id' => $patientRecord->tenant_id,
                'actor_id' => $performedBy->id,
                'actor_role' => $performedBy->role,
                'action' => 'patient_record.routed',
                'resource_type' => 'PatientRecord',
                'resource_id' => $patientRecord->id,
                'description' => "Patient record {$patientRecord->id} routed to department {$nextStep->department_id}",
                'ip_address' => request()->ip(),
                'metadata' => [
                    'from_department_id' => $fromDepartmentId,
                    'to_department_id' => $nextStep->department_id,
                ],
            ]);

            // Dispatch notifications to department staff
            $this->notifyDepartmentStaff($nextStep->department_id, $patientRecord);

            return $patientRecord->fresh();
        });
    }

    /**
     * Route patient record to a specific department (doctor override).
     *
     * @param PatientRecord $patientRecord
     * @param string $departmentId
     * @param User $performedBy
     * @param array|null $metadata
     * @return PatientRecord
     * @throws \Exception
     */
    public function routeToSpecificDepartment(PatientRecord $patientRecord, string $departmentId, User $performedBy, ?array $metadata = null): PatientRecord
    {
        return DB::transaction(function () use ($patientRecord, $departmentId, $performedBy, $metadata) {
            // Verify department exists
            $department = Department::findOrFail($departmentId);

            $fromDepartmentId = $patientRecord->current_department_id;

            // Update patient record
            $patientRecord->update([
                'status' => 'in_transit',
                'current_department_id' => $departmentId,
            ]);

            // Create journey event
            PatientJourneyEvent::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'tenant_id' => $patientRecord->tenant_id,
                'patient_record_id' => $patientRecord->id,
                'from_department_id' => $fromDepartmentId,
                'to_department_id' => $departmentId,
                'event_type' => 'routed',
                'performed_by' => $performedBy->id,
                'notes' => $metadata['notes'] ?? null,
                'metadata' => $metadata,
            ]);

            // Update status back to active
            $patientRecord->update(['status' => 'active']);

            // Audit log
            AuditLog::create([
                'tenant_id' => $patientRecord->tenant_id,
                'actor_id' => $performedBy->id,
                'actor_role' => $performedBy->role,
                'action' => 'patient_record.routed_override',
                'resource_type' => 'PatientRecord',
                'resource_id' => $patientRecord->id,
                'description' => "Patient record {$patientRecord->id} routed to department {$departmentId} by doctor override",
                'ip_address' => request()->ip(),
                'metadata' => [
                    'from_department_id' => $fromDepartmentId,
                    'to_department_id' => $departmentId,
                    'override' => true,
                ],
            ]);

            // Dispatch notifications
            $this->notifyDepartmentStaff($departmentId, $patientRecord);

            return $patientRecord->fresh();
        });
    }

    /**
     * Notify all staff in a department about a new patient record.
     * Public so it can be called after external transactions commit.
     */
    public function notifyDepartmentStaffPublic(string $departmentId, PatientRecord $patientRecord): void
    {
        $this->notifyDepartmentStaff($departmentId, $patientRecord);
    }

    /**
     * Notify all staff in a department about a new patient record.
     *
     * @param string $departmentId
     * @param PatientRecord $patientRecord
     * @return void
     */
    protected function notifyDepartmentStaff(string $departmentId, PatientRecord $patientRecord): void
    {
        try {
            // Get all active staff in the department
            $staffMembers = User::where('department_id', $departmentId)
                ->where('is_active', true)
                ->get();

            foreach ($staffMembers as $staff) {
                Notification::create([
                    'tenant_id' => $patientRecord->tenant_id,
                    'recipient_id' => $staff->id,
                    'type' => 'patient_routed',
                    'channel' => 'in_app',
                    'payload' => [
                        'patient_record_id' => $patientRecord->id,
                        'patient_id' => $patientRecord->patient_id,
                        'department_id' => $departmentId,
                        'message' => 'New patient record assigned to your department',
                    ],
                    'status' => 'pending',
                    'attempts' => 0,
                ]);
            }

            // Dispatch real-time broadcast event (Requirement 4.2, 11.1)
            $patientRecord->loadMissing(['patient', 'currentDepartment']);
            $fromDepartment = $patientRecord->getOriginal('current_department_id')
                ? Department::find($patientRecord->getOriginal('current_department_id'))?->name
                : null;
            $toDepartment = Department::find($departmentId)?->name ?? $departmentId;

            \App\Events\PatientRouted::dispatch(
                $patientRecord->id,
                $patientRecord->patient?->full_name ?? 'Unknown',
                $patientRecord->patient?->patient_number ?? 'N/A',
                $fromDepartment,
                $toDepartment,
                $departmentId,
            );

            // Invalidate department statistics cache for both source and destination departments
            $statsService = app(\App\Services\DepartmentStatisticsService::class);
            $statsService->invalidateCache($departmentId);
            $fromDepartmentId = $patientRecord->getOriginal('current_department_id');
            if ($fromDepartmentId && $fromDepartmentId !== $departmentId) {
                $statsService->invalidateCache($fromDepartmentId);
            }
            
        } catch (\Exception $e) {
            // Log error but don't fail the routing operation
            Log::error('Failed to notify department staff', [
                'department_id' => $departmentId,
                'patient_record_id' => $patientRecord->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
