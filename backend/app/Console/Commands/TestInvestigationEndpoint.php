<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\V1\DepartmentController;
use App\Models\PatientRecord;
use App\Services\WorkflowService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestInvestigationEndpoint extends Command
{
    protected $signature = 'test:investigation-endpoint {patient_record_id}';
    protected $description = 'Test the investigation endpoint to see what it returns';

    public function handle()
    {
        $patientRecordId = $this->argument('patient_record_id');

        $patientRecord = PatientRecord::find($patientRecordId);

        if (!$patientRecord) {
            $this->error('Patient record not found');
            return 1;
        }

        $this->info("Testing investigation endpoint for patient record: {$patientRecordId}");
        $this->line('');

        // Create controller instance
        $workflowService = app(WorkflowService::class);
        $controller = new DepartmentController($workflowService);

        // Call the getInvestigation method
        $response = $controller->getInvestigation($patientRecordId);

        // Get response data
        $statusCode = $response->getStatusCode();
        $content = json_decode($response->getContent(), true);

        $this->info("Status Code: {$statusCode}");
        $this->line('');
        $this->info('Response:');
        $this->line(json_encode($content, JSON_PRETTY_PRINT));

        return 0;
    }
}
