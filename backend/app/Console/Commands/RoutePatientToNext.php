<?php

namespace App\Console\Commands;

use App\Models\PatientRecord;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Console\Command;

class RoutePatientToNext extends Command
{
    protected $signature = 'patient:route-next {patient_name}';
    protected $description = 'Route a specific patient to the next department in their workflow';

    public function handle()
    {
        $patientName = $this->argument('patient_name');
        $tenantId = '2266d360-5a0c-45fc-8b9e-d0e790b98026';
        
        $this->info("🔍 Finding patient: {$patientName}");
        
        // Find patient record
        $patientRecord = PatientRecord::withoutGlobalScopes()
            ->with(['patient', 'currentDepartment', 'workflowTemplate'])
            ->where('tenant_id', $tenantId)
            ->whereHas('patient', function($query) use ($patientName) {
                $query->where('full_name', 'like', "%{$patientName}%");
            })
            ->where('status', 'active')
            ->first();
            
        if (!$patientRecord) {
            $this->error('❌ Patient not found or not active');
            return 1;
        }
        
        $this->info("✅ Found: {$patientRecord->patient->full_name}");
        $this->line("   Current Department: {$patientRecord->currentDepartment->name}");
        $this->line("   Current Step: {$patientRecord->current_step_order}");
        
        if (!$patientRecord->workflowTemplate) {
            $this->error('❌ No workflow template assigned');
            return 1;
        }
        
        // Get a system user
        $systemUser = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('role', 'hospital_admin')
            ->first();
            
        if (!$systemUser) {
            $this->error('❌ No system user found');
            return 1;
        }
        
        if (!$this->confirm('Route this patient to the next department?')) {
            $this->info('Operation cancelled');
            return 0;
        }
        
        try {
            $workflowService = app(WorkflowService::class);
            
            $updatedRecord = $workflowService->routeToNextDepartment(
                $patientRecord,
                $systemUser,
                ['notes' => 'Manual routing via command']
            );
            
            $this->info("\n✅ Successfully routed!");
            $this->line("   New Department: {$updatedRecord->currentDepartment->name}");
            $this->line("   New Step: {$updatedRecord->current_step_order}");
            $this->line("   Status: {$updatedRecord->status}");
            
        } catch (\Exception $e) {
            $this->error("\n❌ Failed to route: {$e->getMessage()}");
            return 1;
        }
        
        return 0;
    }
}
