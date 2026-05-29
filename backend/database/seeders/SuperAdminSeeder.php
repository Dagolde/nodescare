<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the initial Super Admin account.
     *
     * Credentials are read from environment variables so they are never
     * hardcoded in source control.
     *
     * Set these in your .env before running:
     *   SUPER_ADMIN_NAME="System Administrator"
     *   SUPER_ADMIN_EMAIL="admin@hhms.app"
     *   SUPER_ADMIN_PASSWORD="ChangeMe!2024#"
     */
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'admin@hhms.app');

        if (SuperAdmin::where('email', $email)->exists()) {
            $this->command->info("Super Admin [{$email}] already exists — skipping.");
            return;
        }

        SuperAdmin::create([
            'name'     => env('SUPER_ADMIN_NAME', 'System Administrator'),
            'email'    => $email,
            'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'ChangeMe!2024#')),
        ]);

        $this->command->info("Super Admin [{$email}] created successfully.");
        $this->command->warn('Remember to change the default password immediately after first login!');
    }
}
