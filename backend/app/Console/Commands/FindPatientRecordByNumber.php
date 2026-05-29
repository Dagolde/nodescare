<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\PatientRecord;
use Illuminate\Console\Command;

class FindPatientRecordByNumber extends Command
{
    protected $signature = 'find:patient-record {patient_number}';
    protected $description = 'Find patient record ID by patient number';

    public function handle()
    {
        $patientNumber = $this->argument('patient_number');

        // Find patient by patient number
        $patient = Patient::where('patient_number', $patientNumber)->first();

        if (!$patient) {
            $this->error("Patient with number {$patientNumber} not found");
            return 1;
        }

        $this->info("Patient Found:");
        $this->line("Patient ID: {$patient->id}");
        $this->line("Patient Number: {$patient->patient_number}");
        $this->line("Full Name: {$patient->full_name}");
        $this->line('');

        // Get all patient records for this patient
        $records = PatientRecord::where('patient_id', $patient->id)
            ->with(['currentDepartment'])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($records->isEmpty()) {
            $this->warn('No patient records found for this patient');
            return 0;
        }

        $this->info("Patient Records ({$records->count()}):");
        foreach ($records as $record) {
            $this->line('---');
            $this->line("Record ID: {$record->id}");
            $this->line("Status: {$record->status}");
            $this->line("Current Department: {$record->currentDepartment->name ?? 'N/A'}");
            $this->line("Created: {$record->created_at}");
        }

        return 0;
    }
}
