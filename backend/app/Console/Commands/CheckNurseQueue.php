<?php

namespace App\Console\Commands;

use App\Models\PatientRecord;
use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;

class CheckNurseQueue extends Command
{
    protected $signature = 'nurse:check-queue';
    protected $description = 'Check what patients should be visible to nurse/triage staff';

    public function handle()
    {
        $this->info('🔍 Checking nurse/triage queue visibility...');
        
        $tenantId = '2266d360-5a0c-45fc-8b9e-d0e790b98026';
        
        // Find Triage/Nursing department
        $triageDept = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'Triage/Nursing')
            ->first();
            
        if (!$triageDept) {
            $this->error('❌ Triage/Nursing department not found');
            return 1;
        }
        
        $this->info("✅ Found Triage/Nursing department: {$triageDept->id}");
        
        // Find nurse user
        $nurse = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('role', 'nurse')
            ->first();
            
        if (!$nurse) {
            $this->error('❌ Nurse user not found');
            return 1;
        }
        
        $this->info("✅ Found nurse user: {$nurse->name} (Dept: {$nurse->department_id})");
        
        // Check if nurse is assigned to correct department
        if ($nurse->department_id !== $triageDept->id) {
            $this->warn("⚠️ Nurse is assigned to different department!");
            $this->line("  Nurse dept: {$nurse->department_id}");
            $this->line("  Triage dept: {$triageDept->id}");
        }
        
        // Check patients in Triage/Nursing department
        $this->info("\n=== PATIENTS IN TRIAGE/NURSING DEPARTMENT ===");
        
        $patientsInTriage = PatientRecord::withoutGlobalScopes()
            ->with(['patient', 'currentDepartment'])
            ->where('tenant_id', $tenantId)
            ->where('current_department_id', $triageDept->id)
            ->get();
            
        if ($patientsInTriage->isEmpty()) {
            $this->warn('⚠️ No patients found in Triage/Nursing department');
        } else {
            $this->info("✅ Found {$patientsInTriage->count()} patients in Triage/Nursing:");
            foreach ($patientsInTriage as $record) {
                $this->line("  - {$record->patient->full_name} (Status: {$record->status}, Step: {$record->current_step_order})");
            }
        }
        
        // Check what the API would return for nurse
        $this->info("\n=== API QUERY SIMULATION ===");
        
        $apiQuery = PatientRecord::withoutGlobalScopes()
            ->with(['patient', 'currentDepartment', 'workflowTemplate'])
            ->where('current_department_id', $nurse->department_id)
            ->where('status', '!=', 'pending_payment')
            ->orderBy('created_at', 'asc');
            
        $this->line("Query: WHERE current_department_id = '{$nurse->department_id}' AND status != 'pending_payment'");
        
        $apiResults = $apiQuery->get();
        
        if ($apiResults->isEmpty()) {
            $this->warn('⚠️ API query returns no results for nurse');
        } else {
            $this->info("✅ API would return {$apiResults->count()} patients:");
            foreach ($apiResults as $record) {
                $this->line("  - {$record->patient->full_name} (Status: {$record->status})");
            }
        }
        
        // Check recent patient journey events
        $this->info("\n=== RECENT PATIENT ROUTING EVENTS ===");
        
        $recentEvents = \App\Models\PatientJourneyEvent::withoutGlobalScopes()
            ->with(['patientRecord.patient'])
            ->where('tenant_id', $tenantId)
            ->where('to_department_id', $triageDept->id)
            ->where('created_at', '>=', now()->subHours(2))
            ->orderBy('created_at', 'desc')
            ->get();
            
        if ($recentEvents->isEmpty()) {
            $this->warn('⚠️ No recent routing events to Triage/Nursing in last 2 hours');
        } else {
            $this->info("✅ Found {$recentEvents->count()} recent routing events to Triage/Nursing:");
            foreach ($recentEvents as $event) {
                $patientName = $event->patientRecord->patient->full_name ?? 'Unknown';
                $this->line("  - {$patientName} routed at {$event->created_at} (Event: {$event->event_type})");
            }
        }
        
        return 0;
    }
}