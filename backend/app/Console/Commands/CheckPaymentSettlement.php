<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PatientRecord;
use App\Models\PatientJourneyEvent;
use Illuminate\Console\Command;

class CheckPaymentSettlement extends Command
{
    protected $signature = 'payment:check-settlement';
    protected $description = 'Check if payment settlement is working and routing patients correctly';

    public function handle()
    {
        $this->info('🔍 Checking payment settlement and routing...');
        
        $tenantId = '2266d360-5a0c-45fc-8b9e-d0e790b98026';
        
        // Check recent paid invoices
        $this->info("\n=== RECENT PAID INVOICES ===");
        
        $paidInvoices = Invoice::withoutGlobalScopes()
            ->with(['patientRecord.patient', 'patientRecord.currentDepartment'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('updated_at', '>=', now()->subHours(2))
            ->orderBy('updated_at', 'desc')
            ->get();
            
        if ($paidInvoices->isEmpty()) {
            $this->warn('⚠️ No paid invoices found in last 2 hours');
        } else {
            $this->info("✅ Found {$paidInvoices->count()} paid invoices:");
            foreach ($paidInvoices as $invoice) {
                $patient = $invoice->patientRecord->patient;
                $dept = $invoice->patientRecord->currentDepartment;
                $this->line("  - Invoice: {$invoice->invoice_number}");
                $this->line("    Patient: {$patient->full_name}");
                $this->line("    Status: {$invoice->patientRecord->status}");
                $this->line("    Current Dept: {$dept->name}");
                $this->line("    Paid At: {$invoice->updated_at}");
                $this->line("");
            }
        }
        
        // Check patients still in accounts with paid invoices
        $this->info("=== PATIENTS STUCK IN ACCOUNTS WITH PAID INVOICES ===");
        
        $stuckPatients = PatientRecord::withoutGlobalScopes()
            ->with(['patient', 'currentDepartment', 'invoices'])
            ->where('tenant_id', $tenantId)
            ->whereHas('currentDepartment', function($query) {
                $query->where('name', 'like', '%account%');
            })
            ->whereHas('invoices', function($query) {
                $query->where('status', 'paid');
            })
            ->get();
            
        if ($stuckPatients->isEmpty()) {
            $this->info('✅ No patients stuck in accounts with paid invoices');
        } else {
            $this->warn("🚨 Found {$stuckPatients->count()} patients stuck in accounts:");
            foreach ($stuckPatients as $record) {
                $paidInvoices = $record->invoices->where('status', 'paid');
                $this->line("  - {$record->patient->full_name}");
                $this->line("    Status: {$record->status}");
                $this->line("    Department: {$record->currentDepartment->name}");
                $this->line("    Paid Invoices: {$paidInvoices->count()}");
                $this->line("");
            }
        }
        
        // Check recent settlement events
        $this->info("=== RECENT SETTLEMENT EVENTS ===");
        
        $settlementEvents = PatientJourneyEvent::withoutGlobalScopes()
            ->with(['patientRecord.patient'])
            ->where('tenant_id', $tenantId)
            ->where('notes', 'like', '%payment%settled%')
            ->where('created_at', '>=', now()->subHours(2))
            ->orderBy('created_at', 'desc')
            ->get();
            
        if ($settlementEvents->isEmpty()) {
            $this->warn('⚠️ No recent settlement events found in last 2 hours');
        } else {
            $this->info("✅ Found {$settlementEvents->count()} settlement events:");
            foreach ($settlementEvents as $event) {
                $patientName = $event->patientRecord->patient->full_name ?? 'Unknown';
                $this->line("  - {$patientName}: {$event->notes} at {$event->created_at}");
            }
        }
        
        // Check if auto-settlement is working by looking at logs
        $this->info("\n=== CHECKING FOR AUTO-SETTLEMENT ISSUES ===");
        
        // Look for patients with status 'active' but still in accounts
        $activeInAccounts = PatientRecord::withoutGlobalScopes()
            ->with(['patient', 'currentDepartment'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereHas('currentDepartment', function($query) {
                $query->where('name', 'like', '%account%');
            })
            ->get();
            
        if ($activeInAccounts->isNotEmpty()) {
            $this->warn("🚨 Found {$activeInAccounts->count()} active patients still in accounts:");
            foreach ($activeInAccounts as $record) {
                $this->line("  - {$record->patient->full_name} (Status: {$record->status})");
            }
            $this->line("\nThese patients should have been routed after payment!");
        }
        
        return 0;
    }
}