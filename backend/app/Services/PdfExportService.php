<?php

namespace App\Services;

use App\Models\LabResult;
use App\Models\PatientJourneyEvent;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PdfExportService
{
    /**
     * Generate PDF for a lab result.
     *
     * @param LabResult $labResult
     * @return Response
     */
    public function generateLabResultPdf(LabResult $labResult): Response
    {
        try {
            $labResult->load(['patientRecord.patient', 'technician']);

            $tenant = Tenant::find($labResult->tenant_id);
            $patient = $labResult->patientRecord?->patient;

            $data = [
                'hospital_name' => $tenant?->name ?? 'Hospital',
                'hospital_logo' => asset('assets/brand/logo-full.svg'),
                'patient_name' => $patient?->full_name ?? 'Unknown',
                'patient_number' => $patient?->patient_number ?? 'N/A',
                'test_name' => $labResult->test_name,
                'result' => $labResult->result,
                'unit' => $labResult->unit,
                'reference_range' => $labResult->reference_range,
                'status' => $labResult->status,
                'notes' => $labResult->notes,
                'technician_name' => $labResult->technician?->name ?? 'N/A',
                'test_date' => $labResult->created_at->format('d M Y H:i'),
                'export_date' => Carbon::now()->format('d M Y H:i'),
            ];

            $pdf = Pdf::loadView('pdf.lab-result', $data);
            
            $filename = "lab_result_{$patient?->patient_number}_{$labResult->created_at->format('Ymd')}.pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Failed to generate lab result PDF', [
                'lab_result_id' => $labResult->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate PDF for a prescription.
     *
     * @param PatientJourneyEvent $event
     * @return Response
     */
    public function generatePrescriptionPdf(PatientJourneyEvent $event): Response
    {
        try {
            $event->load(['patientRecord.patient', 'performer']);

            $tenant = Tenant::find($event->tenant_id);
            $patient = $event->patientRecord?->patient;
            $metadata = $event->metadata ?? [];

            $data = [
                'hospital_name' => $tenant?->name ?? 'Hospital',
                'hospital_logo' => asset('assets/brand/logo-full.svg'),
                'patient_name' => $patient?->full_name ?? 'Unknown',
                'patient_number' => $patient?->patient_number ?? 'N/A',
                'doctor_name' => $metadata['prescribing_doctor_name'] ?? $event->performer?->name ?? 'N/A',
                'prescription_notes' => $metadata['prescription_notes'] ?? 'No prescription notes',
                'prescription_date' => $event->created_at->format('d M Y H:i'),
                'export_date' => Carbon::now()->format('d M Y H:i'),
            ];

            $pdf = Pdf::loadView('pdf.prescription', $data);
            
            $filename = "prescription_{$patient?->patient_number}_{$event->created_at->format('Ymd')}.pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Failed to generate prescription PDF', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate PDF for a report.
     *
     * @param string $reportType
     * @param array $data
     * @param array $metadata
     * @return Response
     */
    public function generateReportPdf(string $reportType, array $data, array $metadata): Response
    {
        try {
            $tenant = Tenant::find($metadata['tenant_id'] ?? null);

            $pdfData = array_merge($data, [
                'hospital_name' => $tenant?->name ?? 'Hospital',
                'hospital_logo' => asset('assets/brand/logo-full.svg'),
                'report_title' => $metadata['report_title'] ?? 'Department Report',
                'export_date' => Carbon::now()->format('d M Y H:i'),
            ]);

            // Select appropriate view based on report type
            $viewName = match ($reportType) {
                'daily_summary' => 'pdf.daily-summary',
                'staff_performance' => 'pdf.staff-performance',
                'efficiency_metrics' => 'pdf.efficiency-metrics',
                default => 'pdf.generic-report',
            };

            $pdf = Pdf::loadView($viewName, $pdfData);
            
            // Generate filename
            $departmentName = str_replace(' ', '_', $data['department_name'] ?? 'department');
            $dateRange = isset($data['date']) 
                ? $data['date'] 
                : "{$data['start_date']}_to_{$data['end_date']}";
            $filename = "{$reportType}_{$departmentName}_{$dateRange}.pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Failed to generate report PDF', [
                'report_type' => $reportType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
