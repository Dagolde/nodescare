<?php

namespace App\Console\Commands;

use App\Models\PatientRecord;
use App\Models\Invoice;
use App\Http\Controllers\Api\V1\AccountsController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestExistingPatientSettlement extends Command
{
    protected $signature = 'test:existing-settlement';
    protected $description = 'Test auto-settlement with existing pending payment patients';

    public function handle()
    {
        $this->info("=== Testing Auto-Settlement with Existing Data ===");
        
        // Find a patient with pending payment
        $patientRecord = PatientRecord::withoutGlobalScopes()
            ->where('status', 'pending_payment')
            ->with(['patient'])
            ->first();
            
        if (!$patientRecord) {
            $this->warn("No patients with pending payment found. Creating a test scenario...");
            
            // Find any patient record and set it to pending payment for testing
            $patientRecord = PatientRecord::withoutGlobalScopes()
                ->with(['patient'])
                ->first();
                
            if (!$patientRecord) {
                $this->error("No patient records found in database!");
                return 1;
            }
            
            // Set to pending payment for testing
            $patientRecord->update(['status' => 'pending_payment']);
            $this->info("Set patient {$patientRecord->patient->patient_number} to pending_payment for testing");
        }
        
        $this->info("Testing with patient: {$patientRecord->patient->patient_number}");
        $this->info("Patient name: {$patientRecord->patient->full_name}");
        $this->info("Current status: {$patientRecord->status}");
        $this->info("Tenant ID: {$patientRecord->tenant_id}");
        
        // Find or create an invoice for this patient
        $invoice = Invoice::where('patient_record_id', $patientRecord->id)->first();
        
        if (!$invoice) {
            $this->info("No invoice found, creating one...");
            
            $invoice = Invoice::create([
                'id' => \Str::uuid(),
                'tenant_id' => $patientRecord->tenant_id,
                'patient_record_id' => $patientRecord->id,
                'invoice_number' => 'TEST-SETTLEMENT-' . now()->format('YmdHis'),
                'total_amount' => 1000.00,
                'paid_amount' => 0,
                'status' => 'issued',
                'created_by' => 'test-command',
            ]);
            
            $this->info("✅ Created test invoice: {$invoice->invoice_number} (₦1000.00)");
        } else {
            $this->info("Using existing invoice: {$invoice->invoice_number}");
            $this->info("Invoice status: {$invoice->status}");
            $this->info("Amount: ₦{$invoice->paid_amount} / ₦{$invoice->total_amount}");
            
            // Reset invoice for testing if needed
            if ($invoice->status === 'paid') {
                $invoice->update([
                    'paid_amount' => 0,
                    'status' => 'issued'
                ]);
                $this->info("Reset invoice to unpaid for testing");
            }
        }
        
        // Now test payment recording (should trigger auto-settlement)
        $this->info("\n=== Testing Payment Recording (should auto-settle) ===");
        
        try {
            // Create a mock request for full payment
            $paymentAmount = $invoice->total_amount - $invoice->paid_amount;
            
            $request = new Request([
                'amount' => $paymentAmount,
                'payment_method' => 'cash',
                'reference' => 'AUTO-TEST-' . now()->format('His'),
                'notes' => 'Test payment for auto-settlement verification',
            ]);
            
            // Set up the current tenant context
            app()->instance('current_tenant_id', $patientRecord->tenant_id);
            
            $this->info("Recording payment of ₦{$paymentAmount}...");
            
            // Call the controller method
            $controller = new AccountsController();
            $response = $controller->recordPayment($invoice->id, $request);
            
            $responseData = json_decode($response->getContent(), true);
            $statusCode = $response->getStatusCode();
            
            $this->info("Payment API Response Status: {$statusCode}");
            
            if ($statusCode === 201 && $responseData['success']) {
                $this->info("✅ Payment recorded successfully");
                
                // Check if auto-settlement occurred
                if (isset($responseData['data']['auto_settled']) && $responseData['data']['auto_settled']) {
                    $this->info("🎉 AUTO-SETTLEMENT WORKED!");
                } else {
                    $this->warn("⚠️  Payment recorded but auto-settlement flag not found");
                }
                
                // Check final status
                $patientRecord->refresh();
                $invoice->refresh();
                
                $this->info("\n=== Final Status ===");
                $this->info("Patient Status: {$patientRecord->status}");
                $this->info("Invoice Status: {$invoice->status}");
                $this->info("Invoice Paid: ₦{$invoice->paid_amount} / ₦{$invoice->total_amount}");
                
                if ($patientRecord->status === 'active' && $invoice->status === 'paid') {
                    $this->info("✅ AUTO-SETTLEMENT SUCCESSFUL!");
                    $this->info("Patient should now appear in clinical department queue");
                } else {
                    $this->error("❌ Auto-settlement failed");
                    $this->error("Expected: Patient status = 'active', Invoice status = 'paid'");
                    $this->error("Actual: Patient status = '{$patientRecord->status}', Invoice status = '{$invoice->status}'");
                }
                
            } else {
                $this->error("❌ Payment recording failed");
                $this->error("Response: " . $response->getContent());
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}