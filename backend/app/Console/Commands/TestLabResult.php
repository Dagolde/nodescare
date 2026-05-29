<?php

namespace App\Console\Commands;

use App\Models\LabResult;
use App\Models\PatientRecord;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestLabResult extends Command
{
    protected $signature = 'test:lab-result {patient_record_id} {user_id}';
    protected $description = 'Test creating a lab result';

    public function handle()
    {
        $recordId = $this->argument('patient_record_id');
        $userId = $this->argument('user_id');

        try {
            $user = User::findOrFail($userId);
            $patientRecord = PatientRecord::findOrFail($recordId);

            $this->info("User: {$user->name} (ID: {$user->id})");
            $this->info("Patient Record: {$patientRecord->id}");
            $this->info("Tenant ID: " . app('current_tenant_id'));

            DB::beginTransaction();

            $labResult = LabResult::create([
                'id'                => \Illuminate\Support\Str::uuid(),
                'tenant_id'         => app('current_tenant_id'),
                'patient_record_id' => $patientRecord->id,
                'technician_id'     => $user->id,
                'test_name'         => 'Test Result',
                'result'            => 'Positive',
                'unit'              => 'mg/dL',
                'reference_range'   => '0-100',
                'status'            => 'completed',
                'notes'             => 'Test notes',
            ]);

            $this->info("✓ Lab result created: {$labResult->id}");

            DB::commit();

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: {$e->getMessage()}");
            $this->error("File: {$e->getFile()}:{$e->getLine()}");
            return 1;
        }
    }
}
