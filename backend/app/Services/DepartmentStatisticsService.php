<?php

namespace App\Services;

use App\Models\Department;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepartmentStatisticsService
{
    /**
     * Cache TTL in seconds (5 minutes)
     */
    protected const CACHE_TTL = 300;

    /**
     * Get queue statistics for a department.
     *
     * @param string $departmentId
     * @return array
     */
    public function getQueueStatistics(string $departmentId): array
    {
        $cacheKey = "dept_stats:{$departmentId}:queue";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($departmentId) {
            try {
                // Total patients in queue (active status in this department)
                $totalInQueue = PatientRecord::where('current_department_id', $departmentId)
                    ->where('status', 'active')
                    ->count();

                // Patients seen today (completed their step in this department today)
                $patientsSeenToday = PatientJourneyEvent::where('from_department_id', $departmentId)
                    ->where('event_type', 'routed')
                    ->whereDate('created_at', Carbon::today())
                    ->distinct('patient_record_id')
                    ->count('patient_record_id');

                // Average wait time
                $averageWaitTime = $this->calculateAverageWaitTime($departmentId);

                // Staff on duty (active staff in department)
                $staffOnDuty = User::where('department_id', $departmentId)
                    ->where('is_active', true)
                    ->count();

                return [
                    'total_in_queue' => $totalInQueue,
                    'patients_seen_today' => $patientsSeenToday,
                    'average_wait_time' => round($averageWaitTime, 1),
                    'staff_on_duty' => $staffOnDuty,
                ];
            } catch (\Exception $e) {
                Log::error('Failed to get queue statistics', [
                    'department_id' => $departmentId,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'total_in_queue' => 0,
                    'patients_seen_today' => 0,
                    'average_wait_time' => 0,
                    'staff_on_duty' => 0,
                ];
            }
        });
    }

    /**
     * Get patient flow data for the last N days.
     *
     * @param string $departmentId
     * @param int $days
     * @return array
     */
    public function getPatientFlowData(string $departmentId, int $days = 7): array
    {
        $cacheKey = "dept_stats:{$departmentId}:patient_flow:{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($departmentId, $days) {
            try {
                $startDate = Carbon::today()->subDays($days - 1);
                $flowData = [];

                for ($i = 0; $i < $days; $i++) {
                    $date = $startDate->copy()->addDays($i);

                    // Patients who entered the department
                    $entered = PatientJourneyEvent::where('to_department_id', $departmentId)
                        ->whereDate('created_at', $date)
                        ->distinct('patient_record_id')
                        ->count('patient_record_id');

                    // Patients who completed their step (left the department)
                    $completed = PatientJourneyEvent::where('from_department_id', $departmentId)
                        ->where('event_type', 'routed')
                        ->whereDate('created_at', $date)
                        ->distinct('patient_record_id')
                        ->count('patient_record_id');

                    $flowData[] = [
                        'date' => $date->format('Y-m-d'),
                        'entered' => $entered,
                        'completed' => $completed,
                    ];
                }

                return $flowData;
            } catch (\Exception $e) {
                Log::error('Failed to get patient flow data', [
                    'department_id' => $departmentId,
                    'days' => $days,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Get staff workload data for a specific date.
     *
     * @param string $departmentId
     * @param Carbon $date
     * @return array
     */
    public function getStaffWorkloadData(string $departmentId, Carbon $date): array
    {
        $cacheKey = "dept_stats:{$departmentId}:staff_workload:" . $date->format('Y-m-d');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($departmentId, $date) {
            try {
                // Get all staff in the department
                $staff = User::where('department_id', $departmentId)
                    ->where('is_active', true)
                    ->get();

                $workloadData = [];

                foreach ($staff as $staffMember) {
                    // Count patient journey events performed by this staff member
                    $patientCount = PatientJourneyEvent::where('performed_by', $staffMember->id)
                        ->whereDate('created_at', $date)
                        ->distinct('patient_record_id')
                        ->count('patient_record_id');

                    $workloadData[] = [
                        'staff_id' => $staffMember->id,
                        'staff_name' => $staffMember->name,
                        'role' => $staffMember->role,
                        'patient_count' => $patientCount,
                    ];
                }

                // Sort by patient count descending
                usort($workloadData, function ($a, $b) {
                    return $b['patient_count'] <=> $a['patient_count'];
                });

                return $workloadData;
            } catch (\Exception $e) {
                Log::error('Failed to get staff workload data', [
                    'department_id' => $departmentId,
                    'date' => $date->format('Y-m-d'),
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Get recent activity feed for a department.
     *
     * @param string $departmentId
     * @param int $limit
     * @return Collection
     */
    public function getRecentActivityFeed(string $departmentId, int $limit = 10): Collection
    {
        $cacheKey = "dept_stats:{$departmentId}:recent_activity:{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($departmentId, $limit) {
            try {
                return PatientJourneyEvent::with(['patientRecord.patient', 'performer'])
                    ->where(function ($query) use ($departmentId) {
                        $query->where('from_department_id', $departmentId)
                            ->orWhere('to_department_id', $departmentId);
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(function ($event) {
                        return [
                            'id' => $event->id,
                            'event_type' => $event->event_type,
                            'patient_name' => $event->patientRecord?->patient?->full_name ?? 'Unknown',
                            'patient_number' => $event->patientRecord?->patient?->patient_number ?? 'N/A',
                            'staff_name' => $event->performer?->name ?? 'System',
                            'timestamp' => $event->created_at->toIso8601String(),
                            'notes' => $event->notes,
                        ];
                    });
            } catch (\Exception $e) {
                Log::error('Failed to get recent activity feed', [
                    'department_id' => $departmentId,
                    'limit' => $limit,
                    'error' => $e->getMessage(),
                ]);

                return collect([]);
            }
        });
    }

    /**
     * Calculate average wait time for patients in the department queue.
     *
     * @param string $departmentId
     * @return float Average wait time in minutes
     */
    public function calculateAverageWaitTime(string $departmentId): float
    {
        try {
            // Get patients currently in queue
            $patientsInQueue = PatientRecord::where('current_department_id', $departmentId)
                ->where('status', 'active')
                ->get();

            if ($patientsInQueue->isEmpty()) {
                return 0;
            }

            $totalWaitTime = 0;
            $count = 0;

            foreach ($patientsInQueue as $patient) {
                // Find when they arrived at this department
                $arrivalEvent = PatientJourneyEvent::where('patient_record_id', $patient->id)
                    ->where('to_department_id', $departmentId)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($arrivalEvent) {
                    $waitTime = Carbon::now()->diffInMinutes($arrivalEvent->created_at);
                    $totalWaitTime += $waitTime;
                    $count++;
                }
            }

            return $count > 0 ? $totalWaitTime / $count : 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate average wait time', [
                'department_id' => $departmentId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Calculate average service time for patients in a department.
     *
     * @param string $departmentId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float Average service time in minutes
     */
    public function calculateAverageServiceTime(string $departmentId, Carbon $startDate, Carbon $endDate): float
    {
        try {
            // Get all patients who completed their step in this department during the period
            $completedEvents = PatientJourneyEvent::where('from_department_id', $departmentId)
                ->where('event_type', 'routed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            if ($completedEvents->isEmpty()) {
                return 0;
            }

            $totalServiceTime = 0;
            $count = 0;

            foreach ($completedEvents as $completedEvent) {
                // Find when they arrived at this department
                $arrivalEvent = PatientJourneyEvent::where('patient_record_id', $completedEvent->patient_record_id)
                    ->where('to_department_id', $departmentId)
                    ->where('created_at', '<', $completedEvent->created_at)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($arrivalEvent) {
                    $serviceTime = $completedEvent->created_at->diffInMinutes($arrivalEvent->created_at);
                    $totalServiceTime += $serviceTime;
                    $count++;
                }
            }

            return $count > 0 ? $totalServiceTime / $count : 0;
        } catch (\Exception $e) {
            Log::error('Failed to calculate average service time', [
                'department_id' => $departmentId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Calculate staff utilization rate for a department.
     *
     * @param string $departmentId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float Staff utilization percentage
     */
    public function calculateStaffUtilization(string $departmentId, Carbon $startDate, Carbon $endDate): float
    {
        try {
            // Get active staff count
            $staffCount = User::where('department_id', $departmentId)
                ->where('is_active', true)
                ->count();

            if ($staffCount === 0) {
                return 0;
            }

            // Calculate total working hours (assuming 8 hours per day per staff)
            $workingDays = $startDate->diffInDays($endDate) + 1;
            $totalWorkingHours = $staffCount * $workingDays * 8;

            // Calculate total service time (time spent with patients)
            $averageServiceTime = $this->calculateAverageServiceTime($departmentId, $startDate, $endDate);
            
            // Count total patients served
            $patientsServed = PatientJourneyEvent::where('from_department_id', $departmentId)
                ->where('event_type', 'routed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->distinct('patient_record_id')
                ->count('patient_record_id');

            $totalServiceHours = ($averageServiceTime * $patientsServed) / 60;

            // Calculate utilization percentage
            $utilization = $totalWorkingHours > 0 ? ($totalServiceHours / $totalWorkingHours) * 100 : 0;

            return min(100, round($utilization, 1)); // Cap at 100%
        } catch (\Exception $e) {
            Log::error('Failed to calculate staff utilization', [
                'department_id' => $departmentId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Invalidate all cache for a department.
     *
     * @param string $departmentId
     * @return void
     */
    public function invalidateCache(string $departmentId): void
    {
        try {
            // Clear all department statistics cache
            Cache::forget("dept_stats:{$departmentId}:queue");
            
            // Clear patient flow cache for common day ranges
            foreach ([7, 14, 30] as $days) {
                Cache::forget("dept_stats:{$departmentId}:patient_flow:{$days}");
            }
            
            // Clear staff workload cache for today
            $today = Carbon::today()->format('Y-m-d');
            Cache::forget("dept_stats:{$departmentId}:staff_workload:{$today}");
            
            // Clear recent activity cache
            foreach ([10, 20, 50] as $limit) {
                Cache::forget("dept_stats:{$departmentId}:recent_activity:{$limit}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to invalidate department cache', [
                'department_id' => $departmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
