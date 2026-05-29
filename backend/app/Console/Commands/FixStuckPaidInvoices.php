<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PatientRecord;
use Illuminate\Console\Command;

class FixStuckPaidInvoices extends Command
{
    protected $signature = 'invoices:fix-stuck-paid';
    protected $description = 'Fix paid invoices that are stuck and not settled properly';

    public function handle()
    {
        $this->info('🔍 Searching for paid invoices with patients still in pending_payment status...');
        
        // Find all paid invoices where patient record is still pending_payment
        $stuckInvoices = Invoice::with(['patientRecord.patient', 'patientRecord.currentDepartment'])
            ->where('status', 'paid')
            ->whereHas('patientRecord', function($query) {
                $query->where('status', 'pending_payment');
            })
            ->get();
            
        if ($stuckInvoices->isEmpty()) {
            $this->info('✅ No stuck paid invoices found.');
            
            // Also check for active patients still in accounts department
            $activeInAccounts = PatientRecord::with(['patient', 'currentDepartment', 'invoices'])
                ->where('status', 'active')
                ->whereHas('currentDepartment', function($query) {
                    $query->where('name', 'like', '%account%');
                })
                ->get();
                
            if ($activeInAccounts->isNotEmpty()) {
                $this->warn("⚠️  Found {$activeInAccounts->count()} active patients still in accounts department:");
                foreach ($activeInAccounts as $record) {
                    $this->line("  - {$record->patient->full_name} (#{$record->patient->patient_number}) in {$record->currentDepartment->name}");
                }
                
                if ($this->confirm('Do you want to route these active patients to the first clinical department?')) {
                    $this->routeActivePatients($activeInAccounts);
                }
            }
            
            return 0;
        }
        
        $this->warn("🚨 Found {$stuckInvoices->count()} stuck paid invoices:");
        
        foreach ($stuckInvoices as $invoice) {
            $patient = $invoice->patientRecord->patient;
            $dept = $invoice->patientRecord->currentDepartment;
            $this->line("  - Invoice: {$invoice->invoice_number}");
            $this->line("    Patient: {$patient->full_name} (#{$patient->patient_number})");
            $this->line("    Status: {$invoice->patientRecord->status}");
            $this->line("    Department: {$dept->name}");
            $this->line("    Paid: ₦{$invoice->paid_amount} / ₦{$invoice->total_amount}");
            $this->line("");
        }
        
        if (!$this->confirm('Do you want to settle these invoices and route the patients?')) {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        $this->info('🔧 Starting to settle stuck invoices...');
        
        $workflowService = app(\App\Services\WorkflowService::class);
        $successCount = 0;
        $failCount = 0;
        
        foreach ($stuckInvoices as $invoice) {
            $patientRecord = $invoice->patientRecord;
            $patient = $patientRecord->patient;
            
            try {
                $this->line("Processing: {$patient->full_name} - Invoice {$invoice->invoice_number}");
                
                // Check if patient record has workflow template
                if (!$patientRecord->workflow_template_id) {
                    $this->warn("  ⚠️  Missing workflow template, assigning default...");
                    $defaultTemplate = \App\Models\WorkflowTemplate::withoutGlobalScopes()
                        ->where('tenant_id', $patientRecord->tenant_id)
                        ->first();
                        
                    if ($defaultTemplate) {
                        $patientRecord->update(['workflow_template_id' => $defaultTemplate->id]);
                        $patientRecord->refresh();
                        $this->info("  ✅ Assigned: {$defaultTemplate->name}");
                    } else {
                        $this->error("  ❌ No workflow template found, skipping...");
                        $failCount++;
                        continue;
                    }
                }
                
                // Get or create a system user for routing
                $systemUser = \App\Models\User::withoutGlobalScopes()
                    ->where('email', 'system@nodescare.com')
                    ->where('tenant_id', $patientRecord->tenant_id)
                    ->first();
                    
                if (!$systemUser) {
                    $systemUser = \App\Models\User::withoutGlobalScopes()->create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'tenant_id' => $patientRecord->tenant_id,
                        'name' => 'System Auto-Settlement',
                        'email' => 'system@nodescare.com',
                        'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                        'role' => 'accountant',
                        'is_active' => false,
                        'email_verified_at' => now(),
                    ]);
                }
                
                // Ensure patient has correct step_order for their current department before routing
                if (!$patientRecord->current_step_order && $patientRecord->workflow_template_id && $patientRecord->current_department_id) {
                    $currentStep = \App\Models\WorkflowTemplateStep::where('template_id', $patientRecord->workflow_template_id)
                        ->where('department_id', $patientRecord->current_department_id)
                        ->first();
                    if ($currentStep) {
                        $patientRecord->update(['current_step_order' => $currentStep->step_order]);
                        $patientRecord->refresh();
                        $this->info("  ✅ Set current step order to: {$currentStep->step_order}");
                    }
                }
                
                // Route patient to next department directly via WorkflowService
                $updatedRecord = $workflowService->routeToNextDepartment(
                    $patientRecord,
                    $systemUser,
                    ['notes' => 'Manually settled stuck paid invoice. Patient routed to next department.']
                );
                
                $newDept = $updatedRecord->currentDepartment?->name ?? 'Unknown';
                $this->info("  ✅ Routed to: {$newDept}");
                $successCount++;
                
                // Try wallet deduction separately (non-fatal)
                try {
                    $tenant = \App\Models\Tenant::find($patientRecord->tenant_id);
                    if ($tenant) {
                        $walletService = app(\App\Services\WalletService::class);
                        $previousVisits = \App\Models\PatientRecord::withoutGlobalScopes()
                            ->where('tenant_id', $patientRecord->tenant_id)
                            ->where('patient_id', $patientRecord->patient_id)
                            ->where('id', '!=', $patientRecord->id)
                            ->count();
                        if ($previousVisits === 0) {
                            $walletService->deductForNewPatient($patientRecord->tenant_id, $patientRecord->patient_id, $tenant->plan_id);
                        } else {
                            $walletService->deductForReturningPatient($patientRecord->tenant_id, $patientRecord->patient_id, $tenant->plan_id);
                        }
                        $this->info("  ✅ Wallet deducted");
                    }
                } catch (\Exception $walletEx) {
                    $this->warn("  ⚠️  Wallet deduction failed (non-fatal): {$walletEx->getMessage()}");
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed: {$e->getMessage()}");
                $failCount++;
            }
        }
        
        $this->info("\n📊 Settlement Results:");
        $this->info("  ✅ Successfully settled: {$successCount}");
        if ($failCount > 0) {
            $this->error("  ❌ Failed to settle: {$failCount}");
        }
        
        return $failCount > 0 ? 1 : 0;
    }
    
    private function routeActivePatients($patients)
    {
        $successCount = 0;
        $failCount = 0;
        
        foreach ($patients as $patientRecord) {
            try {
                // Get first step from workflow template
                $firstStep = null;
                if ($patientRecord->workflow_template_id) {
                    $firstStep = \App\Models\WorkflowTemplateStep::where('template_id', $patientRecord->workflow_template_id)
                        ->orderBy('step_order', 'asc')
                        ->first();
                }
                
                if (!$firstStep) {
                    // Find any non-accounts department as fallback
                    $fallbackDepartment = \App\Models\Department::withoutGlobalScopes()
                        ->where('tenant_id', $patientRecord->tenant_id)
                        ->where(function($query) {
                            // Try to check is_active if column exists, otherwise just get any department
                            try {
                                $query->where('is_active', true);
                            } catch (\Exception $e) {
                                // Column doesn't exist, continue without this filter
                            }
                        })
                        ->where('name', 'not like', '%account%')
                        ->first();
                        
                    if (!$fallbackDepartment) {
                        throw new \Exception('No suitable department found for routing.');
                    }
                    
                    $firstStep = (object) [
                        'department_id' => $fallbackDepartment->id,
                        'step_order' => 1,
                    ];
                }
                
                $patientRecord->update([
                    'current_department_id' => $firstStep->department_id,
                    'current_step_order' => $firstStep->step_order,
                ]);
                
                \App\Models\PatientJourneyEvent::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $patientRecord->tenant_id,
                    'patient_record_id' => $patientRecord->id,
                    'from_department_id' => $patientRecord->current_department_id,
                    'to_department_id' => $firstStep->department_id,
                    'event_type' => 'routed',
                    'performed_by' => 'system',
                    'notes' => 'Manually routed from accounts to first clinical department.',
                ]);
                
                $this->info("  ✅ Routed {$patientRecord->patient->full_name} to clinical department");
                $successCount++;
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to route {$patientRecord->patient->full_name}: {$e->getMessage()}");
                $failCount++;
            }
        }
        
        $this->info("\n📊 Routing Results:");
        $this->info("  ✅ Successfully routed: {$successCount}");
        if ($failCount > 0) {
            $this->error("  ❌ Failed to route: {$failCount}");
        }
    }
}