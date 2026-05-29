<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserNotificationPreferences;
use Illuminate\Database\Seeder;

class UserNotificationPreferencesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Populates default notification preferences for all existing HOD users.
     * All preferences are enabled by default.
     */
    public function run(): void
    {
        $this->command->info('Seeding user notification preferences...');

        // Get all HOD users (users with is_hod = true)
        $hodUsers = User::where('is_hod', true)->get();

        if ($hodUsers->isEmpty()) {
            $this->command->warn('No HOD users found. Skipping notification preferences seeding.');
            return;
        }

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($hodUsers as $user) {
            // Check if preferences already exist for this user
            $existingPreferences = UserNotificationPreferences::where('user_id', $user->id)->first();

            if ($existingPreferences) {
                $this->command->info("  Skipping {$user->name} (already has preferences)");
                $skippedCount++;
                continue;
            }

            // Create default notification preferences (all enabled)
            UserNotificationPreferences::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'patient_arrivals_enabled' => true,
                'staff_actions_enabled' => true,
                'system_alerts_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => true,
                'in_app_enabled' => true,
            ]);

            $this->command->info("  Created notification preferences for {$user->name}");
            $createdCount++;
        }

        $this->command->info("User notification preferences seeding completed!");
        $this->command->info("  Created: {$createdCount} users");
        $this->command->info("  Skipped: {$skippedCount} users (already configured)");
    }
}
