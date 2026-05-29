<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Http\Controllers\Api\V1\AccountsController;
use Illuminate\Console\Command;

class TestInvoiceSettlement extends Command
{
    protected $signature = 'invoices:test-settlement {invoice_number}';
    protected $description = 'Test settlement of a specific invoice by invoice number';

    public function handle()
    {
        $invoiceNumber = $this->argument('invoice_number');
        
        $invoice = Invoice::with(['patientRecord.patient', 'patientRecord.currentDepartment'])
            ->where('invoice_number', $invoiceNumber)
            ->first();
            
        if (!$invoice) {
            $this->error("Invoice {$invoiceNumber} not found!");
            return 1;
        }
        
        $patient = $invoice->patientRecord->patient;
        $dept = $invoice->patientRecord->currentDepartment;
        
        $this->info("=== Invoice Information ===");
        $this->info("Invoice: {$invoice->invoice_number}");
        $this->info("Status: {$invoice->status}");
        $this->info("Total: ₦{$invoice->total_amount}");
        $this->info("Paid: ₦{$invoice->paid_amount}");
        $this->info("");
        $this->info("=== Patient Information ===");
        $this->info("Name: {$patient->full_name}");
        $this->info("Number: {$patient->patient_number}");
        $this->info("Record Status: {$invoice->patientRecord->status}");
        $this->info("Current Department: {$dept->name}");
        $this->info("");
        
        if ($invoice->status !== 'paid') {
            $this->error("Invoice is not fully paid (status: {$invoice->status})");
            return 1;
        }
        
        if (!$this->confirm('Do you want to attempt settlement of this invoice?')) {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        try {
            $this->info('Attempting to settle invoice...');
            
            $accountsController = new AccountsController();
            $response = $accountsController->settleInvoice($invoice->id);
            
            $responseData = json_decode($response->getContent(), true);
            
            if ($response->getStatusCode() === 200) {
                $this->info('✅ Invoice settled successfully!');
                $this->info('Response: ' . json_encode($responseData, JSON_PRETTY_PRINT));
            } else {
                $this->error('❌ Settlement failed!');
                $this->error('Status Code: ' . $response->getStatusCode());
                $this->error('Response: ' . json_encode($responseData, JSON_PRETTY_PRINT));
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Exception during settlement: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}