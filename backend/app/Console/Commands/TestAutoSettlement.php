<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Http\Controllers\Api\V1\AccountsController;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TestAutoSettlement extends Command
{
    protected $signature = 'test:auto-settlement';
    protected $description = 'Test auto-settlement by creating a test scenario and recording payment via API';

    public function handle()
    {
        $this->info("=== Testing Auto-Settlement Flow ===");
        
        // Get the tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->error("No tenant found!");
            return 1;
        }
        
        $this->info("Using tenant: {$tenant->name} ({$tenant->id})");
        
        try {
            DB::beginTransaction();
            
            // 1. Create a test patient
            $patient = Patient::withoutGlobalScopes()->create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'patient_number' => 'TEST-' . now()->format('YmdHis'),
                'full_name' => 'Test Auto Settlement Patient',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'phone' => '08012345678',
                'address' => 'Test Address',
                'chief_complaint' => 'Test complaint for auto-settlement',
                'created_by' => 'test-command',
            ]);
            
            $this->info("✅ Created test patient: {$patient->patient_number}");
            
            // 2. Create a patient record
            $patientRecord = PatientRecord::withoutGlobalScopes()->create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'status' => 'pending_payment',
                'workflow_template_id' => null, // We'll handle this
                'current_department_id' => null,
                'current_step_order' => 0,
                'registered_by' => 'test-command',
                'created_by' => 'test-command',
            ]);
            
            $this->info("✅ Created patient record: {$patientRecord->id}");
            
            // 3. Create an invoice
            $invoice = Invoice::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'patient_record_id' => $patientRecord->id,
                'invoice_number' => 'AUTO-TEST-' . now()->format('YmdHis'),
                'total_amount' => 500.00,
                'paid_amount' => 0,
                'status' => 'issued',
                'created_by' => 'test-command',
            ]);
            
            // Add invoice item
            InvoiceItem::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'description' => 'Test consultation fee',
                'unit_price' => 500.00,
                'quantity' => 1,
                'total_price' => 500.00,
            ]);
            
            $this->info("✅ Created invoice: {$invoice->invoice_number} (₦500.00)");
            
            DB::commit();
            
            // 4. Now test the payment recording via API (this should trigger auto-settlement)
            $this->info("\n=== Testing Payment Recording (should auto-settle) ===");
            
            // Create a mock request
            $request = new Request([
                'amount' => 500.00,
                'payment_method' => 'cash',
                'reference' => 'AUTO-TEST-' . now()->format('His'),
                'notes' => 'Test payment for auto-settlement',
            ]);
            
            // Set up the current tenant context
            app()->instance('current_tenant_id', $tenant->id);
            
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
                    $this->info("Patient should now be routed to clinical department");
                } else {
                    $this->warn("⚠️  Payment recorded but auto-settlement didn't trigger");
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
                } else {
                    $this->error("❌ Auto-settlement failed - patient still pending");
                }
                
            } else {
                $this->error("❌ Payment recording failed");
                $this->error("Response: " . $response->getContent());
            }
            
            // 5. Cleanup
            $this->info("\n=== Cleaning up test data ===");
            $invoice->delete();
            $patientRecord->delete();
            $patient->delete();
            $this->info("✅ Test data cleaned up");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}