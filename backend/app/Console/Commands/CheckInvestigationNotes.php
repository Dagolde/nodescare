<?php

namespace App\Console\Commands;

use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use Illuminate\Console\Command;

class CheckInvestigationNotes extends Command
{
    protected $signature = 'check:investigation-notes {patient_record_id?}';
    protected $description = 'Check investigation notes for a patient record';

    public function handle()
    {
        $patientRecordId = $this->argument('patient_record_id');

        if (!$patientRecordId) {
            // Show all patient records with pending_payment or in lab
            $this->info('Recent patient records:');
            $records = PatientRecord::with(['patient', 'currentDepartment'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            foreach ($records as $record) {
                $this->line(sprintf(
                    'ID: %s | Patient: %s | Status: %s | Dept: %s',
                    $record->id,
                    $record->patient->full_name ?? 'N/A',
                    $record->status,
                    $record->currentDepartment->name ?? 'N/A'
                ));
            }

            $patientRecordId = $this->ask('Enter patient record ID to check');
        }

        $patientRecord = PatientRecord::with('patient')->find($patientRecordId);

        if (!$patientRecord) {
            $this->error('Patient record not found');
            return 1;
        }

        $this->info("Patient Record: {$patientRecord->id}");
        $this->info("Patient: {$patientRecord->patient->full_name ?? 'N/A'}");
        $this->info("Status: {$patientRecord->status}");
        $this->info("Current Department: {$patientRecord->current_department_id}");
        $this->info("Return To Department: {$patientRecord->return_to_department_id}");
        $this->info("Payment Context: {$patientRecord->pending_payment_context}");
        $this->line('');

        // Get all journey events with metadata
        $this->info('Journey Events with Metadata:');
        $events = PatientJourneyEvent::where('patient_record_id', $patientRecordId)
            ->whereNotNull('metadata')
            ->orderBy('created_at', 'desc')
            ->with('performer')
            ->get();

        if ($events->isEmpty()) {
            $this->warn('No journey events with metadata found');
        } else {
            foreach ($events as $event) {
                $this->line('---');
                $this->line("Event ID: {$event->id}");
                $this->line("Type: {$event->event_type}");
                $this->line("From Dept: {$event->from_department_id}");
                $this->line("To Dept: {$event->to_department_id}");
                $this->line("Performed By: {$event->performer->name ?? 'N/A'}");
                $this->line("Created: {$event->created_at}");
                $this->line("Notes: {$event->notes}");
                
                if ($event->metadata) {
                    $this->line('Metadata:');
                    foreach ($event->metadata as $key => $value) {
                        $this->line("  - {$key}: {$value}");
                    }
                }
            }
        }

        $this->line('');
        $this->info('Checking for investigation notes specifically:');
        $investigationEvent = PatientJourneyEvent::where('patient_record_id', $patientRecordId)
            ->whereNotNull('metadata->investigation_notes')
            ->orderBy('created_at', 'desc')
            ->with('performer')
            ->first();

        if ($investigationEvent) {
            $this->info('✓ Investigation notes found!');
            $this->line("Investigation Notes: {$investigationEvent->metadata['investigation_notes']}");
            $this->line("Requested By: {$investigationEvent->performer->name ?? 'N/A'}");
            $this->line("Requested At: {$investigationEvent->created_at}");
        } else {
            $this->warn('✗ No investigation notes found in any journey event');
        }

        return 0;
    }
}
