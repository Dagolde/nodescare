<?php

namespace App\Console\Commands;

use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStep;
use App\Models\Department;
use Illuminate\Console\Command;

class CheckWorkflowOrder extends Command
{
    protected $signature = 'workflow:check-order';
    protected $description = 'Check the workflow template step order';

    public function handle()
    {
        $this->info('🔍 Checking workflow template step order...');
        
        $tenantId = '2266d360-5a0c-45fc-8b9e-d0e790b98026';
        
        // Find the Standard Patient Journey template
        $template = WorkflowTemplate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'Standard Patient Journey')
            ->first();
            
        if (!$template) {
            $this->error('❌ Standard Patient Journey template not found');
            return 1;
        }
        
        $this->info("✅ Found template: {$template->name}");
        $this->info("\n📋 Current Workflow Steps:");
        
        $steps = WorkflowTemplateStep::where('template_id', $template->id)
            ->orderBy('step_order')
            ->get();
            
        foreach ($steps as $step) {
            $dept = Department::withoutGlobalScopes()->find($step->department_id);
            $deptName = $dept ? $dept->name : 'MISSING DEPARTMENT';
            $this->line("  {$step->step_order}. {$deptName}");
        }
        
        // Check if Triage/Nursing comes before Doctor Consultation
        $triageStep = $steps->first(function($step) {
            $dept = Department::withoutGlobalScopes()->find($step->department_id);
            return $dept && stripos($dept->name, 'triage') !== false || stripos($dept->name, 'nursing') !== false;
        });
        
        $doctorStep = $steps->first(function($step) {
            $dept = Department::withoutGlobalScopes()->find($step->department_id);
            return $dept && stripos($dept->name, 'doctor') !== false;
        });
        
        if ($triageStep && $doctorStep) {
            if ($triageStep->step_order > $doctorStep->step_order) {
                $this->error("\n❌ ISSUE FOUND: Triage/Nursing (step {$triageStep->step_order}) comes AFTER Doctor Consultation (step {$doctorStep->step_order})");
                $this->warn("Patients will skip Triage/Nursing and go directly to Doctor!");
                
                if ($this->confirm('Do you want to fix the step order?')) {
                    $this->fixStepOrder($template->id, $tenantId);
                }
            } else {
                $this->info("\n✅ Step order is correct: Triage/Nursing (step {$triageStep->step_order}) comes before Doctor Consultation (step {$doctorStep->step_order})");
            }
        } else {
            if (!$triageStep) {
                $this->warn("\n⚠️ No Triage/Nursing step found in workflow");
            }
            if (!$doctorStep) {
                $this->warn("\n⚠️ No Doctor Consultation step found in workflow");
            }
        }
        
        return 0;
    }
    
    protected function fixStepOrder($templateId, $tenantId)
    {
        $this->info("\n🔧 Fixing workflow step order...");
        
        // Get all departments
        $frontDesk = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', '%front%desk%')
            ->first();
            
        $accounts = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', '%account%')
            ->first();
            
        $triage = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function($query) {
                $query->where('name', 'like', '%triage%')
                      ->orWhere('name', 'like', '%nursing%');
            })
            ->first();
            
        $doctor = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', '%doctor%')
            ->first();
            
        $lab = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', '%lab%')
            ->first();
            
        $pharmacy = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', '%pharm%')
            ->first();
        
        if (!$frontDesk || !$accounts || !$triage || !$doctor || !$lab || !$pharmacy) {
            $this->error('❌ Not all required departments found');
            return;
        }
        
        // Delete existing steps
        WorkflowTemplateStep::where('template_id', $templateId)->delete();
        
        // Create new steps in correct order
        $correctOrder = [
            ['dept' => $frontDesk, 'order' => 1],
            ['dept' => $accounts, 'order' => 2],
            ['dept' => $triage, 'order' => 3],
            ['dept' => $doctor, 'order' => 4],
            ['dept' => $lab, 'order' => 5],
            ['dept' => $pharmacy, 'order' => 6],
        ];
        
        foreach ($correctOrder as $step) {
            WorkflowTemplateStep::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'template_id' => $templateId,
                'department_id' => $step['dept']->id,
                'step_order' => $step['order'],
            ]);
            
            $this->info("  ✅ Created step {$step['order']}: {$step['dept']->name}");
        }
        
        $this->info("\n✅ Workflow step order fixed!");
    }
}
