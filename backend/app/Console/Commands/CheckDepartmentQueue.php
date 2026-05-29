<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\PatientRecord;
use App\Models\Department;
use Illuminate\Console\Command;

class CheckDepartmentQueue extends Command
{
    protected $signature = 'debug:department-queue {user_email}';
    protected $description = 'Debug department queue for a specific user';

    public function handle()
    {
        $email = $this->argument('user_email');
        
        // Find user by email
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email {$email} not found!");
            return 1;
        }
        
        $this->info("=== User Information ===");
        $this->info("ID: {$user->id}");
        $this->info("Name: {$user->name}");
        $this->info("Email: {$user->email}");
        $this->info("Role: {$user->role}");
        $this->info("Department ID: " . ($user->department_id ?? 'NULL'));
        $this->info("Is Active: " . ($user->is_active ? 'Yes' : 'No'));
        $this->info("Tenant ID: {$user->tenant_id}");
        
        if (!$user->department_id) {
            $this->error("\n🚨 ISSUE: User has no department assigned!");
            $this->info("This user needs to be assigned to a department to see the queue.");
            return 1;
        }
        
        // Get department info
        $department = Department::find($user->department_id);
        if ($department) {
            $this->info("Department Name: {$department->name}");
        } else {
            $this->error("Department not found!");
            return 1;
        }
        
        // Check patient records in this department
        $patientRecords = PatientRecord::with(['patient', 'currentDepartment'])
            ->where('current_department_id', $user->department_id)
            ->where('status', '!=', 'pending_payment')
            ->orderBy('created_at', 'asc')
            ->get();
            
        $this->info("\n=== Department Queue ===");
        $this->info("Total patients in queue: {$patientRecords->count()}");
        
        if ($patientRecords->count() > 0) {
            $this->info("\nPatients:");
            foreach ($patientRecords as $record) {
                $patient = $record->patient;
                $this->info("- {$patient->full_name} (#{$patient->patient_number}) - Status: {$record->status}");
            }
        } else {
            $this->warn("No patients found in this department's queue.");
            
            // Check if there are any patients in other departments
            $allPatients = PatientRecord::with(['patient', 'currentDepartment'])
                ->where('status', '!=', 'pending_payment')
                ->get();
                
            $this->info("\nAll active patients in system: {$allPatients->count()}");
            if ($allPatients->count() > 0) {
                $this->info("Patients in other departments:");
                foreach ($allPatients as $record) {
                    $patient = $record->patient;
                    $dept = $record->currentDepartment;
                    $this->info("- {$patient->full_name} in {$dept->name} (ID: {$dept->id})");
                }
            }
        }
        
        return 0;
    }
}