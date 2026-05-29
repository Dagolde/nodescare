<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Department;
use App\Models\WorkflowTemplate;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Console\Command;

class TestWorkflowProgression extends Command
{
    protected $signature = 'workflow:test-progression';
    protected $description = 'Test the complete workflow progression from Front Desk to final department';

    public function handle()
    {
        $this->info('🧪 Testing workflow progression...');
        
        // Get tenant ID
        $tenantId = '2266d360-5a0c-45fc-8b9e-d0e790b98026';
        
        // Get workflow template
        $workflowTemplate = WorkflowTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'Standard Patient Journey')
            ->first();
            
        if (!$workflowTemplate) {
            $this->error('❌ Standard Patient Journey workflow template not found');
            return 1;
        }
        
        $this->info("✅ Found workflow template: {$workflowTemplate->name}");
        
        // Get departments in order
        $steps = $workflowTemplate->steps()->orderBy('step_order')->with('department')->get();
        
        $this->info("\n📋 Workflow Steps:");
        foreach ($steps as $step) {
            $this->line("  {$step->step_order}. {$step->department->name}");
        }
        
        // Create a test patient record
        $patient = Patient::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->first();
            
        if (!$patient) {
            $this->error('❌ No patients found for testing');
            return 1;
        }
        
        // Get a system user for testing
        $systemUser = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('role', 'hospital_admin')
            ->first();
            
        if (!$systemUser) {
            $this->error('❌ No system user found for testing');
            return 1;
        }

        // Create test patient record
        $patientRecord = PatientRecord::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenantId,
            'patient_id' => $patient->id,
            'workflow_template_id' => $workflowTemplate->id,
            'current_department_id' => $steps->first()->department_id,
            'current_step_order' => 1,
            'status' => 'active',
            'chief_complaint' => 'Test workflow progression',
            'registered_by' => $systemUser->id,
        ]);
        
        $workflowService = app(WorkflowService::class);
        
        $this->info("\n🏥 Created test patient record: {$patientRecord->id}");
        $this->info("Patient: {$patient->full_name}");
        
        // Test progression through each step
        $currentStep = 1;
        $maxSteps = $steps->count();
        
        while ($currentStep < $maxSteps) {
            $currentDept = $steps->where('step_order', $currentStep)->first();
            $this->info("\n🔄 Current Step {$currentStep}: {$currentDept->department->name}");
            
            try {
                $updatedRecord = $workflowService->routeToNextDepartment(
                    $patientRecord->fresh(),
                    $systemUser,
                    ['notes' => "Test progression from step {$currentStep}"]
                );
                
                $nextDept = Department::withoutGlobalScopes()->find($updatedRecord->current_department_id);
                $this->info("  ✅ Routed to: {$nextDept->name} (Step {$updatedRecord->current_step_order})");
                
                $patientRecord = $updatedRecord;
                $currentStep++;
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to route: {$e->getMessage()}");
                break;
            }
        }
        
        // Test final completion
        if ($currentStep === $maxSteps) {
            $this->info("\n🏁 Testing final completion...");
            try {
                $finalRecord = $workflowService->routeToNextDepartment(
                    $patientRecord->fresh(),
                    $systemUser,
                    ['notes' => 'Test final completion']
                );
                
                if ($finalRecord->status === 'completed') {
                    $this->info("  ✅ Patient successfully discharged");
                } else {
                    $this->warn("  ⚠️ Patient not discharged, status: {$finalRecord->status}");
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed final completion: {$e->getMessage()}");
            }
        }
        
        // Clean up test record
        $patientRecord->fresh()->delete();
        $this->info("\n🧹 Cleaned up test patient record");
        
        $this->info("\n✅ Workflow progression test completed!");
        
        return 0;
    }
}