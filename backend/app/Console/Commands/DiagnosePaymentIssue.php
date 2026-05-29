<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PatientRecord;
use Illuminate\Console\Command;

class DiagnosePaymentIssue extends Command
{
    protected $signature = 'payment:diagnose {patient_name?}';
    protected $description = 'Diagnose payment and settlement issues for a patient';

    public function handle()
    {
        $patientName = $this->argument('patient_name');
        
        $this->info('🔍 Diagnosing payment and settlement issues...');
        $this->newLine();
        
        // Find patients with paid invoices but still in pending_payment status
        $stuckPatients = PatientRecord::withoutGlobalScopes()
            ->where('status', 'pending_payment')
            ->whereHas('invoices', function($query) {
                $query->where('status', 'paid');
            })
            ->with(['patient', 'invoices', 'currentDepartment', 'workflowTemplate'])
            ->get();
            
        if ($stuckPatients->isEmpty()) {
            $this->info('✅ No patients stuck with paid invoices in pending_payment status');
        } else {
            $this->error("❌ Found {$stuckPatients->count()} patients stuck with paid invoices:");
            $this->newLine();
            
            foreach ($stuckPatients as $patient) {
                $this->line("Patient: {$patient->patient->full_name}");
                $this->line("  Status: {$patient->status}");
                $this->line("  Current Dept: " . ($patient->currentDepartment->name ?? 'None'));
                $this->line("  Current Step: {$patient->current_step_order}");
                $this->line("  Workflow: " . ($patient->workflowTemplate->name ?? 'None'));
                
                $paidInvoices = $patient->invoices->where('status', 'paid');
                foreach ($paidInvoices as $invoice) {
                    $this->line("  Invoice: {$invoice->invoice_number} - Paid: ₦{$invoice->paid_amount}");
                }
                $this->newLine();
            }
        }
        
        // Check recent payment recordings
        $this->info('📋 Recent Payment Recordings (last 10):');
        $recentPayments = \App\Models\InvoicePayment::withoutGlobalScopes()
            ->with(['invoice.patientRecord.patient'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        foreach ($recentPayments as $payment) {
            $invoice = $payment->invoice;
            $patientRecord = $invoice->patientRecord;
            $this->line("  {$patientRecord->patient->full_name}: ₦{$payment->amount} via {$payment->payment_method}");
            $this->line("    Invoice: {$invoice->invoice_number} - Status: {$invoice->status}");
            $this->line("    Patient Status: {$patientRecord->status}");
            $this->line("    Paid at: {$payment->created_at}");
            $this->newLine();
        }
        
        return 0;
    }
}
