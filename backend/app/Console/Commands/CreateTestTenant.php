<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTestTenant extends Command
{
    protected $signature = 'tenant:create-test {--id=2266d360-5a0c-45fc-8b9e-d0e790b98026}';
    protected $description = 'Create a test tenant for development';

    public function handle()
    {
        $tenantId = $this->option('id');
        
        // Check if tenant already exists
        $existingTenant = Tenant::find($tenantId);
        if ($existingTenant) {
            $this->info("Tenant {$tenantId} already exists: {$existingTenant->name}");
            return;
        }

        // Create a default plan if none exists
        $plan = Plan::first();
        if (!$plan) {
            $plan = Plan::create([
                'id' => Str::uuid(),
                'name' => 'Basic Plan',
                'description' => 'Basic hospital management plan',
                'price' => 0,
                'features' => ['patient_management', 'invoicing', 'basic_reports'],
                'is_active' => true,
            ]);
            $this->info("Created default plan: {$plan->name}");
        }

        // Create the test tenant
        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Test Hospital',
            'subdomain' => 'test-hospital-' . substr($tenantId, 0, 8),
            'plan_id' => $plan->id,
            'status' => 'active',
            'database_name' => 'care', // Using same database
        ]);

        $this->info("Created test tenant: {$tenant->name} ({$tenant->id})");
        $this->info("Subdomain: {$tenant->subdomain}");
        $this->info("Status: {$tenant->status}");
    }
}