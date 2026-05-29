<?php

namespace App\Console\Commands;

use App\Models\PatientRecord;
use App\Models\Department;
use Illuminate\Console\Command;

class MovePatientToDepartment extends Command
{
    protected $signature = 'patient:move {patient_name} {department_name}';
    protected $description = 'Move a patient to a specific department for testing';

    public function handle()
    {
        $patientName = $this->argument('patient_name');
        $departmentName = $this->argument('department_name');
        $tenantId = '2266d360-5a0c-45fc-8b9e-d0e790b98026';
        
        // Find patient
        $patientRecord = PatientRecord::withoutGlobalScopes()
            ->with(['patient', 'currentDepartment'])
            ->where('tenant_id', $tenantId)
            ->whereHas('patient', function($query) use ($patientName) {
                $query->where('full_name', 'like', "%{$patientName}%");
            })
            ->where('status', 'active')
            ->first();
            
        if (!$patientRecord) {
            $this->error('❌ Patient not found');
            return 1;
        }
        
        // Find department
        $department = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', "%{$departmentName}%")
            ->first();
            
        if (!$department) {
            $this->error('❌ Department not found');
            return 1;
        }
        
        $this->info("Moving: {$patientRecord->patient->full_name}");
        $this->info("From: {$patientRecord->currentDepartment->name}");
        $this->info("To: {$department->name}");
        
        if (!$this->confirm('Proceed?')) {
            return 0;
        }
        
        // Update patient record
        $patientRecord->update([
            'current_department_id' => $department->id,
        ]);
        
        $this->info("✅ Patient moved successfully!");
        
        return 0;
    }
}
