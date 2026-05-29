<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TestPaymentFlow extends Command
{
    protected $signature = 'test:payment-flow {patient_number}';
    protected $description = 'Test the complete payment and settlement flow for a patient';

    public function handle()
    {
        $patientNumber = $this->argument('patient_number');
        
        // Find patient by number
        $patient = Patient::where('patient_number', $patientNumber)->first();
        
        if (!$patient) {
            $this->error("Patient with number {$patientNumber} not found!");
            return 1;
        }
        
        // Get latest patient record
        $patientRecord = PatientRecord::where('patient_id', $patient->id)
            ->latest()
            ->first();
            
        if (!$patientRecord) {
            $this->error("No patient record found for this patient.");
            return 1;
        }
        
        $this->info("=== Testing Payment Flow ===");
        $this->info("Patient: {$patient->full_name} ({$patient->patient_number})");
        $this->info("Patient Record ID: {$patientRecord->id}");
        $this->info("Current Status: {$patientRecord->status}");
        $this->info("Tenant ID: {$patientRecord->tenant_id}");
        
        try {
            DB::beginTransaction();
            
            // 1. Create a test invoice if none exists
            $invoice = Invoice::where('patient_record_id', $patientRecord->id)->first();
            
            if (!$invoice) {
                $this->info("\n1. Creating test invoice...");
                
                $invoice = Invoice::create([
                    'id'                => Str::uuid(),
                    'tenant_id'         => $patientRecord->tenant_id,
                    'patient_record_id' => $patientRecord->id,
                    'invoice_number'    => 'TEST-' . now()->format('YmdHis'),
                    'total_amount'      => 1000.00,
                    'paid_amount'       => 0,
                    'status'            => 'issued',
                    'created_by'        => 'test-command',
                ]);
                
                // Add test invoice item
                InvoiceItem::create([
                    'id'          => Str::uuid(),
                    'tenant_id'   => $patientRecord->tenant_id,
                    'invoice_id'  => $invoice->id,
                    'description' => 'Test consultation fee',
                    'unit_price'  => 1000.00,
                    'quantity'    => 1,
                    'total_price' => 1000.00,
                ]);
                
                $this->info("✅ Test invoice created: {$invoice->invoice_number}");
            } else {
                $this->info("\n1. Using existing invoice: {$invoice->invoice_number}");
            }
            
            // 2. Record a payment
            if ($invoice->paid_amount < $invoice->total_amount) {
                $this->info("\n2. Recording test payment...");
                
                $paymentAmount = $invoice->total_amount - $invoice->paid_amount;
                
                $payment = InvoicePayment::create([
                    'id'             => Str::uuid(),
                    'tenant_id'      => $patientRecord->tenant_id,
                    'invoice_id'     => $invoice->id,
                    'amount'         => $paymentAmount,
                    'payment_method' => 'cash',
                    'reference'      => 'TEST-' . now()->format('His'),
                    'notes'          => 'Test payment via command',
                    'accountant_id'  => 'test-command',
                ]);
                
                // Update invoice
                $newPaid = $invoice->paid_amount + $paymentAmount;
                $invoice->update([
                    'paid_amount' => $newPaid,
                    'status'      => $newPaid >= $invoice->total_amount ? 'paid' : 'partially_paid',
                ]);
                
                $this->info("✅ Payment recorded: ₦{$paymentAmount}");
                $this->info("Invoice status: {$invoice->fresh()->status}");
            } else {
                $this->info("\n2. Invoice already fully paid");
            }
            
            DB::commit();
            
            // 3. Test settlement via API call
            $this->info("\n3. Testing settlement...");
            
            $response = $this->call('artisan', [
                'command' => 'tinker',
                '--execute' => "
                    \$controller = new App\\Http\\Controllers\\Api\\V1\\AccountsController();
                    \$response = \$controller->settleInvoice('{$invoice->id}');
                    echo 'Settlement response: ' . \$response->getStatusCode() . PHP_EOL;
                    echo \$response->getContent() . PHP_EOL;
                "
            ]);
            
            // 4. Check final status
            $patientRecord->refresh();
            $invoice->refresh();
            
            $this->info("\n=== Final Status ===");
            $this->info("Patient Status: {$patientRecord->status}");
            $this->info("Invoice Status: {$invoice->status}");
            $this->info("Invoice Paid: ₦{$invoice->paid_amount} / ₦{$invoice->total_amount}");
            
            if ($patientRecord->status === 'active' && $invoice->status === 'paid') {
                $this->info("✅ Payment flow completed successfully!");
            } else {
                $this->error("❌ Payment flow incomplete - check logs for errors");
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Test failed: {$e->getMessage()}");
            return 1;
        }
        
        return 0;
    }
}