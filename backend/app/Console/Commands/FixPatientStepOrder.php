<?php

namespace App\Console\Commands;

use App\Models\PatientRecord;
use App\Models\Department;
use App\Models\WorkflowTemplateStep;
use Illuminate\Console\Command;

class FixPatientStepOrder extends Command
{
    protected $signature = 'patients:fix-step-order';
    protected $description = 'Fix patient records with incorrect step orders';

    public function handle()
    {
        $this->info('🔍 Checking patient step orders...');
        
        $tenantId = '2266d360-5a0c-45fc-8b9e-d0e790b98026';
        
        // Get all active patients
        $patients = PatientRecord::withoutGlobalScopes()
            ->with(['patient', 'currentDepartment', 'workflowTemplate'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
            
        if ($patients->isEmpty()) {
            $this->info('✅ No active patients found');
            return 0;
        }
        
        $this->info("Found {$patients->count()} active patients\n");
        
        $issuesFound = 0;
        
        foreach ($patients as $patient) {
            $currentDept = $patient->currentDepartment;
            $currentStepOrder = $patient->current_step_order;
            
            if (!$patient->workflowTemplate) {
                $this->warn("⚠️  {$patient->patient->full_name}: No workflow template assigned");
                continue;
            }
            
            // Find the correct step order for current department
            $correctStep = WorkflowTemplateStep::where('template_id', $patient->workflow_template_id)
                ->where('department_id', $currentDept->id)
                ->first();
                
            if (!$correctStep) {
                $this->error("❌ {$patient->patient->full_name}: Current department '{$currentDept->name}' not in workflow");
                $issuesFound++;
                continue;
            }
            
            if ($currentStepOrder != $correctStep->step_order) {
                $this->warn("⚠️  {$patient->patient->full_name}:");
                $this->line("   Current Dept: {$currentDept->name}");
                $this->line("   Recorded Step: {$currentStepOrder}");
                $this->line("   Correct Step: {$correctStep->step_order}");
                $issuesFound++;
                
                if ($this->confirm("   Fix this patient's step order?", true)) {
                    $patient->update(['current_step_order' => $correctStep->step_order]);
                    $this->info("   ✅ Fixed!");
                }
            } else {
                $this->info("✅ {$patient->patient->full_name}: {$currentDept->name} (Step {$currentStepOrder}) - Correct");
            }
        }
        
        if ($issuesFound === 0) {
            $this->info("\n✅ All patient step orders are correct!");
        } else {
            $this->info("\n📊 Found {$issuesFound} issues");
        }
        
        return 0;
    }
}
