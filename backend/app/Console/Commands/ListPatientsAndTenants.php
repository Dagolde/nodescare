<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Tenant;
use App\Models\Invoice;
use Illuminate\Console\Command;

class ListPatientsAndTenants extends Command
{
    protected $signature = 'list:patients-tenants';
    protected $description = 'List available patients and tenants for testing';

    public function handle()
    {
        $this->info("=== Available Tenants ===");
        $tenants = Tenant::take(10)->get();
        
        if ($tenants->count() > 0) {
            foreach ($tenants as $tenant) {
                $this->info("ID: {$tenant->id}");
                $this->info("Name: {$tenant->name}");
                $this->info("Status: {$tenant->status}");
                $this->info("---");
            }
        } else {
            $this->warn("No tenants found in database.");
        }
        
        $this->info("\n=== Available Patients ===");
        $patients = Patient::withoutGlobalScopes()->with(['records' => function($query) {
            $query->latest()->take(1);
        }])->take(10)->get();
        
        if ($patients->count() > 0) {
            foreach ($patients as $patient) {
                $this->info("Patient Number: {$patient->patient_number}");
                $this->info("Name: {$patient->full_name}");
                $this->info("Phone: {$patient->phone}");
                
                $latestRecord = $patient->records->first();
                if ($latestRecord) {
                    $this->info("Latest Record Status: {$latestRecord->status}");
                    $this->info("Tenant ID: {$latestRecord->tenant_id}");
                } else {
                    $this->warn("No patient records found");
                }
                $this->info("---");
            }
        } else {
            $this->warn("No patients found in database.");
        }
        
        $this->info("\n=== Patients with Pending Payments ===");
        $pendingPatients = PatientRecord::withoutGlobalScopes()->where('status', 'pending_payment')
            ->with(['patient'])
            ->take(5)
            ->get();
            
        if ($pendingPatients->count() > 0) {
            foreach ($pendingPatients as $record) {
                $this->info("Patient Number: {$record->patient->patient_number}");
                $this->info("Name: {$record->patient->full_name}");
                $this->info("Record ID: {$record->id}");
                $this->info("Status: {$record->status}");
                $this->info("Tenant ID: {$record->tenant_id}");
                
                // Check if they have invoices
                $invoices = Invoice::where('patient_record_id', $record->id)->get();
                $this->info("Invoices: {$invoices->count()}");
                
                foreach ($invoices as $invoice) {
                    $this->info("  - Invoice #{$invoice->invoice_number}: {$invoice->status} (₦{$invoice->paid_amount}/₦{$invoice->total_amount})");
                }
                
                $this->info("---");
            }
        } else {
            $this->warn("No patients with pending payments found.");
        }
        
        return 0;
    }
}