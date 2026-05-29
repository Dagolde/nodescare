<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\PatientRecord;
use App\Models\User;
use Illuminate\Console\Command;

class TestRoutePatient extends Command
{
    protected $signature = 'test:route-patient {patient_record_id} {user_id} {target_department_name}';
    protected $description = 'Test routing a patient to a specific department';

    public function handle()
    {
        $recordId = $this->argument('patient_record_id');
        $userId = $this->argument('user_id');
        $targetDeptName = $this->argument('target_department_name');

        try {
            $user = User::findOrFail($userId);
            $patientRecord = PatientRecord::findOrFail($recordId);

            $this->info("User: {$user->name} (Role: {$user->role}, Dept ID: {$user->department_id})");
            $this->info("Patient Record: {$patientRecord->id} (Current Dept: {$patientRecord->current_department_id}, Status: {$patientRecord->status})");

            // Check role
            if (!in_array($user->role, ['doctor', 'hod']) && !$user->is_hod) {
                $this->error("❌ User role check failed: User is {$user->role}, not doctor or HOD");
                return 1;
            }
            $this->info("✓ User role check passed");

            // Check department assignment
            if ($patientRecord->current_department_id !== $user->department_id) {
                $this->error("❌ Department check failed: Patient is in dept {$patientRecord->current_department_id}, user is in dept {$user->department_id}");
                return 1;
            }
            $this->info("✓ Department check passed");

            // Check status
            if ($patientRecord->status === 'in_transit') {
                $this->error("❌ Status check failed: Patient is in_transit");
                return 1;
            }
            $this->info("✓ Status check passed");

            // Find target department
            $targetDept = Department::where('tenant_id', $patientRecord->tenant_id)
                ->where('name', 'like', "%{$targetDeptName}%")
                ->first();

            if (!$targetDept) {
                $this->error("❌ Target department not found: {$targetDeptName}");
                return 1;
            }
            $this->info("✓ Target department found: {$targetDept->name} (ID: {$targetDept->id})");

            $this->info("\n✅ All checks passed! Patient can be routed to {$targetDept->name}");
            return 0;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }
}
