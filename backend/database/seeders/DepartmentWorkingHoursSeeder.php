<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentWorkingHours;
use Illuminate\Database\Seeder;

class DepartmentWorkingHoursSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Populates default working hours for all existing departments.
     * Default schedule: Monday-Friday 8:00-17:00, Saturday-Sunday closed.
     */
    public function run(): void
    {
        $this->command->info('Seeding department working hours...');

        // Get all departments
        $departments = Department::all();

        if ($departments->isEmpty()) {
            $this->command->warn('No departments found. Skipping working hours seeding.');
            return;
        }

        $defaultSchedule = [
            // Sunday
            ['day_of_week' => 0, 'is_open' => false, 'opening_time' => null, 'closing_time' => null],
            // Monday
            ['day_of_week' => 1, 'is_open' => true, 'opening_time' => '08:00:00', 'closing_time' => '17:00:00'],
            // Tuesday
            ['day_of_week' => 2, 'is_open' => true, 'opening_time' => '08:00:00', 'closing_time' => '17:00:00'],
            // Wednesday
            ['day_of_week' => 3, 'is_open' => true, 'opening_time' => '08:00:00', 'closing_time' => '17:00:00'],
            // Thursday
            ['day_of_week' => 4, 'is_open' => true, 'opening_time' => '08:00:00', 'closing_time' => '17:00:00'],
            // Friday
            ['day_of_week' => 5, 'is_open' => true, 'opening_time' => '08:00:00', 'closing_time' => '17:00:00'],
            // Saturday
            ['day_of_week' => 6, 'is_open' => false, 'opening_time' => null, 'closing_time' => null],
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($departments as $department) {
            // Check if working hours already exist for this department
            $existingCount = DepartmentWorkingHours::where('department_id', $department->id)->count();

            if ($existingCount > 0) {
                $this->command->info("  Skipping {$department->name} (already has working hours)");
                $skippedCount++;
                continue;
            }

            // Create working hours for all 7 days
            foreach ($defaultSchedule as $schedule) {
                DepartmentWorkingHours::create([
                    'tenant_id' => $department->tenant_id,
                    'department_id' => $department->id,
                    'day_of_week' => $schedule['day_of_week'],
                    'is_open' => $schedule['is_open'],
                    'opening_time' => $schedule['opening_time'],
                    'closing_time' => $schedule['closing_time'],
                ]);
            }

            $this->command->info("  Created working hours for {$department->name}");
            $createdCount++;
        }

        $this->command->info("Department working hours seeding completed!");
        $this->command->info("  Created: {$createdCount} departments");
        $this->command->info("  Skipped: {$skippedCount} departments (already configured)");
    }
}
