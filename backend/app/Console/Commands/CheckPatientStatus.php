<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Invoice;
use Illuminate\Console\Command;

class CheckPatientStatus extends Command
{
    protected $signature = 'patient:status {patient_number}';
    protected $description = 'Check patient record and invoice status';

    public function handle()
    {
        $patientNumber = $this->argument('patient_number');
        
        // Find patient by number
        $patient = Patient::where('patient_number', $patientNumber)->first();
        
        if (!$patient) {
            $this->error("Patient with number {$patientNumber} not found!");
            return 1;
        }
        
        $this->info("=== Patient Information ===");
        $this->info("ID: {$patient->id}");
        $this->info("Number: {$patient->patient_number}");
        $this->info("Name: {$patient->full_name}");
        $this->info("Phone: {$patient->phone}");
        $this->info("Created: {$patient->created_at}");
        
        // Get latest patient record
        $patientRecord = PatientRecord::where('patient_id', $patient->id)
            ->with(['currentDepartment'])
            ->latest()
            ->first();
            
        if (!$patientRecord) {
            $this->warn("No patient record found for this patient.");
            return 0;
        }
        
        $this->info("\n=== Patient Record Information ===");
        $this->info("Record ID: {$patientRecord->id}");
        $this->info("Status: {$patientRecord->status}");
        $this->info("Current Department: " . ($patientRecord->currentDepartment->name ?? 'N/A'));
        $this->info("Tenant ID: {$patientRecord->tenant_id}");
        $this->info("Created: {$patientRecord->created_at}");
        $this->info("Updated: {$patientRecord->updated_at}");
        
        // Get invoices for this patient record
        $invoices = Invoice::where('patient_record_id', $patientRecord->id)
            ->with(['payments'])
            ->get();
            
        if ($invoices->count() > 0) {
            $this->info("\n=== Invoices ===");
            foreach ($invoices as $invoice) {
                $this->info("Invoice #{$invoice->invoice_number}:");
                $this->info("  - ID: {$invoice->id}");
                $this->info("  - Status: {$invoice->status}");
                $this->info("  - Total: ₦{$invoice->total_amount}");
                $this->info("  - Paid: ₦{$invoice->paid_amount}");
                $this->info("  - Created: {$invoice->created_at}");
                
                if ($invoice->payments->count() > 0) {
                    $this->info("  - Payments:");
                    foreach ($invoice->payments as $payment) {
                        $this->info("    * ₦{$payment->amount} via {$payment->payment_method} on {$payment->created_at}");
                    }
                } else {
                    $this->warn("  - No payments recorded");
                }
                $this->info("");
            }
        } else {
            $this->warn("No invoices found for this patient record.");
        }
        
        // Check if settlement is needed
        $paidInvoice = $invoices->where('status', 'paid')->first();
        if ($paidInvoice && $patientRecord->status === 'pending_payment') {
            $this->error("\n🚨 ISSUE DETECTED:");
            $this->error("Invoice is marked as 'paid' but patient record is still 'pending_payment'");
            $this->error("This means the settleInvoice endpoint was not called!");
            $this->info("\nTo fix this, call: POST /api/v1/invoices/{$paidInvoice->id}/settle");
        } elseif ($patientRecord->status === 'pending_payment') {
            $this->warn("\n⚠️  Patient is still pending payment. Invoice needs to be settled after payment.");
        } else {
            $this->info("\n✅ Patient status looks correct.");
        }
        
        return 0;
    }
}