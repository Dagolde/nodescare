<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PatientRecord;
use App\Models\Department;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStep;
use Illuminate\Console\Command;

class DiagnoseWorkflowIssues extends Command
{
    protected $signature = 'workflow:diagnose';
    protected $description = 'Diagnose workflow and department setup issues';

    public function handle()
    {
        $this->info('🔍 Diagnosing workflow and department setup...');
        
        // Check departments
        $this->info("\n=== DEPARTMENTS ===");
        $departments = Department::withoutGlobalScopes()->get();
        
        if ($departments->isEmpty()) {
            $this->error('❌ No departments found in the system!');
        } else {
            $this->info("✅ Found {$departments->count()} departments:");
            foreach ($departments as $dept) {
                // Check if is_active column exists
                $activeStatus = '✅ Active'; // Default to active if column doesn't exist
                try {
                    $activeStatus = ($dept->is_active ?? true) ? '✅ Active' : '❌ Inactive';
                } catch (\Exception $e) {
                    $activeStatus = '⚠️ Unknown (is_active column missing)';
                }
                $this->line("  - {$dept->name} (Tenant: {$dept->tenant_id}) {$activeStatus}");
            }
        }
        
        // Check workflow templates
        $this->info("\n=== WORKFLOW TEMPLATES ===");
        $templates = WorkflowTemplate::withoutGlobalScopes()->with('steps')->get();
        
        if ($templates->isEmpty()) {
            $this->error('❌ No workflow templates found in the system!');
        } else {
            $this->info("✅ Found {$templates->count()} workflow templates:");
            foreach ($templates as $template) {
                // Check if is_active column exists
                $activeStatus = '✅ Active'; // Default to active if column doesn't exist
                try {
                    $activeStatus = ($template->is_active ?? true) ? '✅ Active' : '❌ Inactive';
                } catch (\Exception $e) {
                    $activeStatus = '⚠️ Unknown (is_active column missing)';
                }
                
                $this->line("  - {$template->name} (Tenant: {$template->tenant_id}) {$activeStatus}");
                $this->line("    Steps: {$template->steps->count()}");
                
                foreach ($template->steps->sortBy('step_order') as $step) {
                    $dept = Department::withoutGlobalScopes()->find($step->department_id);
                    $deptName = $dept ? $dept->name : 'MISSING DEPARTMENT';
                    $this->line("      {$step->step_order}. {$deptName}");
                }
            }
        }
        
        // Check stuck patients
        $this->info("\n=== STUCK PATIENTS ANALYSIS ===");
        
        $stuckPatients = PatientRecord::with(['patient', 'currentDepartment', 'invoices', 'workflowTemplate'])
            ->where('status', 'pending_payment')
            ->whereHas('invoices', function($query) {
                $query->where('status', 'paid');
            })
            ->get();
            
        if ($stuckPatients->isEmpty()) {
            $this->info('✅ No stuck patients found.');
        } else {
            $this->warn("⚠️  Found {$stuckPatients->count()} stuck patients:");
            
            foreach ($stuckPatients as $record) {
                $this->line("\n  Patient: {$record->patient->full_name} (#{$record->patient->patient_number})");
                $this->line("  Status: {$record->status}");
                $this->line("  Department: " . ($record->currentDepartment ? $record->currentDepartment->name : 'N/A'));
                $this->line("  Workflow Template: " . ($record->workflowTemplate ? $record->workflowTemplate->name : 'NONE ASSIGNED'));
                
                if ($record->workflowTemplate) {
                    $firstStep = WorkflowTemplateStep::where('template_id', $record->workflow_template_id)
                        ->orderBy('step_order', 'asc')
                        ->first();
                        
                    if ($firstStep) {
                        $firstDept = Department::withoutGlobalScopes()->find($firstStep->department_id);
                        $this->line("  First Step Department: " . ($firstDept->name ?? 'MISSING'));
                    } else {
                        $this->error("  ❌ No steps found in workflow template!");
                    }
                } else {
                    $this->error("  ❌ No workflow template assigned!");
                }
                
                $paidInvoices = $record->invoices->where('status', 'paid');
                $this->line("  Paid Invoices: {$paidInvoices->count()}");
            }
        }
        
        // Check active patients in accounts
        $activeInAccounts = PatientRecord::with(['patient', 'currentDepartment'])
            ->where('status', 'active')
            ->whereHas('currentDepartment', function($query) {
                $query->where('name', 'like', '%account%');
            })
            ->get();
            
        if ($activeInAccounts->isNotEmpty()) {
            $this->warn("\n⚠️  Found {$activeInAccounts->count()} active patients still in accounts:");
            foreach ($activeInAccounts as $record) {
                $this->line("  - {$record->patient->full_name} in {$record->currentDepartment->name}");
            }
        }
        
        return 0;
    }
}