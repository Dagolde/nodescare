<?php

namespace App\Services;

use App\Models\Department;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportGenerationService
{
    protected DepartmentStatisticsService $statisticsService;

    public function __construct(DepartmentStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Generate daily summary report for a department.
     *
     * @param string $departmentId
     * @param Carbon $date
     * @return array
     */
    public function generateDailySummary(string $departmentId, Carbon $date): array
    {
        try {
            $department = Department::findOrFail($departmentId);

            // Get all patients who visited the department on this date
            $patientEvents = PatientJourneyEvent::with(['patientRecord.patient'])
                ->where('to_department_id', $departmentId)
                ->whereDate('created_at', $date)
                ->get();

            $patientList = [];
            $totalWaitTime = 0;
            $totalServiceTime = 0;
            $waitTimeCount = 0;
            $serviceTimeCount = 0;

            foreach ($patientEvents as $arrivalEvent) {
                $patientRecord = $arrivalEvent->patientRecord;
                $patient = $patientRecord?->patient;

                if (!$patient) {
                    continue;
                }

                // Find departure event
                $departureEvent = PatientJourneyEvent::where('patient_record_id', $patientRecord->id)
                    ->where('from_department_id', $departmentId)
                    ->where('event_type', 'routed')
                    ->where('created_at', '>', $arrivalEvent->created_at)
                    ->whereDate('created_at', $date)
                    ->first();

                $timeOfArrival = $arrivalEvent->created_at;
                $timeOfDeparture = $departureEvent?->created_at;
                $serviceTime = null;

                if ($timeOfDeparture) {
                    $serviceTime = $timeOfDeparture->diffInMinutes($timeOfArrival);
                    $totalServiceTime += $serviceTime;
                    $serviceTimeCount++;
                }

                // Calculate wait time (time from arrival to first action)
                $firstAction = PatientJourneyEvent::where('patient_record_id', $patientRecord->id)
                    ->where('performed_by', '!=', null)
                    ->where('created_at', '>', $arrivalEvent->created_at)
                    ->whereDate('created_at', $date)
                    ->orderBy('created_at')
                    ->first();

                $waitTime = null;
                if ($firstAction) {
                    $waitTime = $firstAction->created_at->diffInMinutes($timeOfArrival);
                    $totalWaitTime += $waitTime;
                    $waitTimeCount++;
                }

                $patientList[] = [
                    'patient_name' => $patient->full_name,
                    'patient_number' => $patient->patient_number,
                    'time_of_arrival' => $timeOfArrival->format('H:i'),
                    'time_of_departure' => $timeOfDeparture?->format('H:i'),
                    'wait_time' => $waitTime,
                    'service_time' => $serviceTime,
                    'chief_complaint' => $patientRecord->chief_complaint ?? 'N/A',
                    'outcome' => $departureEvent ? 'Completed' : 'In Progress',
                ];
            }

            $averageWaitTime = $waitTimeCount > 0 ? round($totalWaitTime / $waitTimeCount, 1) : 0;
            $averageServiceTime = $serviceTimeCount > 0 ? round($totalServiceTime / $serviceTimeCount, 1) : 0;

            return [
                'department_name' => $department->name,
                'date' => $date->format('Y-m-d'),
                'total_patients' => count($patientList),
                'average_wait_time' => $averageWaitTime,
                'average_service_time' => $averageServiceTime,
                'patient_list' => $patientList,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate daily summary report', [
                'department_id' => $departmentId,
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate staff performance report for a department.
     *
     * @param string $departmentId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function generateStaffPerformance(string $departmentId, Carbon $startDate, Carbon $endDate): array
    {
        try {
            $department = Department::findOrFail($departmentId);

            // Get all staff in the department
            $staff = User::where('department_id', $departmentId)
                ->where('is_active', true)
                ->get();

            $staffMetrics = [];

            foreach ($staff as $staffMember) {
                // Count patients handled
                $patientsHandled = PatientJourneyEvent::where('performed_by', $staffMember->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->distinct('patient_record_id')
                    ->count('patient_record_id');

                // Count notes added
                $notesAdded = PatientJourneyEvent::where('performed_by', $staffMember->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNotNull('notes')
                    ->where('notes', '!=', '')
                    ->count();

                // Calculate average service time for this staff member
                $serviceEvents = PatientJourneyEvent::where('performed_by', $staffMember->id)
                    ->where('from_department_id', $departmentId)
                    ->where('event_type', 'routed')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $totalServiceTime = 0;
                $serviceTimeCount = 0;

                foreach ($serviceEvents as $departureEvent) {
                    $arrivalEvent = PatientJourneyEvent::where('patient_record_id', $departureEvent->patient_record_id)
                        ->where('to_department_id', $departmentId)
                        ->where('created_at', '<', $departureEvent->created_at)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($arrivalEvent) {
                        $serviceTime = $departureEvent->created_at->diffInMinutes($arrivalEvent->created_at);
                        $totalServiceTime += $serviceTime;
                        $serviceTimeCount++;
                    }
                }

                $averageServiceTime = $serviceTimeCount > 0 ? round($totalServiceTime / $serviceTimeCount, 1) : 0;

                $staffMetrics[] = [
                    'staff_name' => $staffMember->name,
                    'role' => $staffMember->role,
                    'patients_handled' => $patientsHandled,
                    'average_service_time' => $averageServiceTime,
                    'notes_added' => $notesAdded,
                ];
            }

            // Sort by patients handled descending
            usort($staffMetrics, function ($a, $b) {
                return $b['patients_handled'] <=> $a['patients_handled'];
            });

            return [
                'department_name' => $department->name,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'staff_metrics' => $staffMetrics,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate staff performance report', [
                'department_id' => $departmentId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate efficiency metrics report for a department.
     *
     * @param string $departmentId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function generateEfficiencyMetrics(string $departmentId, Carbon $startDate, Carbon $endDate): array
    {
        try {
            $department = Department::findOrFail($departmentId);

            // Calculate current period metrics
            $averageWaitTime = $this->statisticsService->calculateAverageWaitTime($departmentId);
            $averageServiceTime = $this->statisticsService->calculateAverageServiceTime($departmentId, $startDate, $endDate);
            $staffUtilization = $this->statisticsService->calculateStaffUtilization($departmentId, $startDate, $endDate);

            // Calculate patient throughput (patients per day)
            $totalPatients = PatientJourneyEvent::where('to_department_id', $departmentId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->distinct('patient_record_id')
                ->count('patient_record_id');

            $days = $startDate->diffInDays($endDate) + 1;
            $throughput = $days > 0 ? round($totalPatients / $days, 1) : 0;

            // Calculate previous period metrics for trend indicators
            $periodLength = $startDate->diffInDays($endDate) + 1;
            $previousStartDate = $startDate->copy()->subDays($periodLength);
            $previousEndDate = $startDate->copy()->subDay();

            $previousAverageServiceTime = $this->statisticsService->calculateAverageServiceTime($departmentId, $previousStartDate, $previousEndDate);
            $previousStaffUtilization = $this->statisticsService->calculateStaffUtilization($departmentId, $previousStartDate, $previousEndDate);

            $previousTotalPatients = PatientJourneyEvent::where('to_department_id', $departmentId)
                ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
                ->distinct('patient_record_id')
                ->count('patient_record_id');

            $previousThroughput = $periodLength > 0 ? round($previousTotalPatients / $periodLength, 1) : 0;

            // Calculate trend indicators
            $currentMetrics = [
                'average_service_time' => $averageServiceTime,
                'throughput' => $throughput,
                'staff_utilization' => $staffUtilization,
            ];

            $previousMetrics = [
                'average_service_time' => $previousAverageServiceTime,
                'throughput' => $previousThroughput,
                'staff_utilization' => $previousStaffUtilization,
            ];

            $trendIndicators = $this->calculateTrendIndicators($currentMetrics, $previousMetrics);

            return [
                'department_name' => $department->name,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'metrics' => [
                    'average_wait_time' => round($averageWaitTime, 1),
                    'average_service_time' => round($averageServiceTime, 1),
                    'patient_throughput' => $throughput,
                    'staff_utilization' => round($staffUtilization, 1),
                ],
                'trend_indicators' => $trendIndicators,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate efficiency metrics report', [
                'department_id' => $departmentId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate trend indicators by comparing current and previous metrics.
     *
     * @param array $currentMetrics
     * @param array $previousMetrics
     * @return array
     */
    public function calculateTrendIndicators(array $currentMetrics, array $previousMetrics): array
    {
        $trends = [];

        foreach ($currentMetrics as $metric => $currentValue) {
            $previousValue = $previousMetrics[$metric] ?? 0;

            if ($previousValue == 0) {
                $trends[$metric] = [
                    'direction' => 'stable',
                    'percentage_change' => 0,
                ];
                continue;
            }

            $percentageChange = (($currentValue - $previousValue) / $previousValue) * 100;

            // For service time, lower is better (improving)
            // For throughput and utilization, higher is better (improving)
            $direction = 'stable';
            if (abs($percentageChange) > 5) { // Only consider significant changes (>5%)
                if ($metric === 'average_service_time') {
                    $direction = $percentageChange < 0 ? 'improving' : 'declining';
                } else {
                    $direction = $percentageChange > 0 ? 'improving' : 'declining';
                }
            }

            $trends[$metric] = [
                'direction' => $direction,
                'percentage_change' => round($percentageChange, 1),
            ];
        }

        return $trends;
    }
}
