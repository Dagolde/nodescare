<?php

namespace App\Console\Commands;

use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use Illuminate\Console\Command;

class AddInvestigationNotesToPatient extends Command
{
    protected $signature = 'fix:add-investigation-notes {patient_record_id} {notes}';
    protected $description = 'Add investigation notes to a patient record that is missing them';

    public function handle()
    {
        $patientRecordId = $this->argument('patient_record_id');
        $notes = $this->argument('notes');

        $patientRecord = PatientRecord::find($patientRecordId);

        if (!$patientRecord) {
            $this->error('Patient record not found');
            return 1;
        }

        $patientName = $patientRecord->patient ? $patientRecord->patient->full_name : 'N/A';
        $departmentName = $patientRecord->currentDepartment ? $patientRecord->currentDepartment->name : 'N/A';
        
        $this->info("Patient Record: {$patientRecord->id}");
        $this->info("Patient: {$patientName}");
        $this->info("Current Department: {$departmentName}");
        $this->line('');

        // Check if investigation notes already exist
        $existing = PatientJourneyEvent::where('patient_record_id', $patientRecordId)
            ->whereNotNull('metadata->investigation_notes')
            ->first();

        if ($existing) {
            $this->warn('Investigation notes already exist for this patient:');
            $this->line($existing->metadata['investigation_notes']);
            
            if (!$this->confirm('Do you want to add new investigation notes anyway?')) {
                return 0;
            }
        }

        // Create a new journey event with investigation notes
        $event = PatientJourneyEvent::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $patientRecord->tenant_id,
            'patient_record_id' => $patientRecord->id,
            'from_department_id' => $patientRecord->current_department_id,
            'to_department_id' => $patientRecord->current_department_id,
            'event_type' => 'note_added',
            'performed_by' => auth()->id() ?? \App\Models\User::first()->id,
            'notes' => 'Investigation notes added manually',
            'metadata' => [
                'investigation_notes' => $notes,
            ],
        ]);

        $this->info('✓ Investigation notes added successfully!');
        $this->line("Event ID: {$event->id}");
        $this->line("Notes: {$notes}");

        return 0;
    }
}

# On your production server

