<?php

namespace Tests\Feature\Jobs;

use App\Jobs\TenantProvisioningJob;
use App\Models\Department;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStep;
use App\Notifications\TenantOnboardingNotification;
use App\Notifications\TenantProvisioningFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TenantProvisioningJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable actual notification sending
        Notification::fake();
    }

    public function test_tenant_provisioning_creates_default_departments(): void
    {
        // Create a tenant
        $tenant = Tenant::create([
            'name' => 'Test Hospital',
            'subdomain' => 'test-hospital',
            'status' => 'active',
        ]);

        // Dispatch the job
        $job = new TenantProvisioningJob(
            $tenant,
            'admin@test.com',
            'Test Admin'
        );

        $job->handle();

        // Switch to tenant context and verify departments were created
        $tenant->run(function () {
            $this->assertDatabaseCount('departments', 6);
            
            $this->assertDatabaseHas('departments', [
                'code' => 'ACCTS',
                'type' => 'accounts',
            ]);
            
            $this->assertDatabaseHas('departments', [
                'code' => 'FRONT',
                'type' => 'front_desk',
            ]);
            
            $this->assertDatabaseHas('departments', [
                'code' => 'TRIAGE',
                'type' => 'triage',
            ]);
            
            $this->assertDatabaseHas('departments', [
                'code' => 'DOC',
                'type' => 'clinical',
            ]);
            
            $this->assertDatabaseHas('departments', [
                'code' => 'PHARM',
                'type' => 'pharmacy',
            ]);
            
            $this->assertDatabaseHas('departments', [
                'code' => 'LAB',
                'type' => 'laboratory',
            ]);
        });
    }

    public function test_tenant_provisioning_creates_default_workflow_template(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Hospital',
            'subdomain' => 'test-hospital',
            'status' => 'active',
        ]);

        $job = new TenantProvisioningJob(
            $tenant,
            'admin@test.com',
            'Test Admin'
        );

        $job->handle();

        $tenant->run(function () {
            // Verify workflow template was created
            $this->assertDatabaseCount('workflow_templates', 1);
            
            $this->assertDatabaseHas('workflow_templates', [
                'name' => 'Standard Patient Journey',
                'is_default' => true,
            ]);

            // Verify workflow steps were created
            $this->assertDatabaseCount('workflow_template_steps', 4);
            
            $template = WorkflowTemplate::first();
            $steps = WorkflowTemplateStep::where('template_id', $template->id)
                ->orderBy('step_order')
                ->get();
            
            $this->assertEquals(4, $steps->count());
            $this->assertEquals(1, $steps[0]->step_order);
            $this->assertEquals(4, $steps[3]->step_order);
            $this->assertTrue($steps[3]->is_terminal);
        });
    }

    public function test_tenant_provisioning_creates_hospital_admin_user(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Hospital',
            'subdomain' => 'test-hospital',
            'status' => 'active',
        ]);

        $job = new TenantProvisioningJob(
            $tenant,
            'admin@test.com',
            'Test Admin'
        );

        $job->handle();

        $tenant->run(function () {
            // Verify admin user was created
            $this->assertDatabaseCount('users', 1);
            
            $this->assertDatabaseHas('users', [
                'name' => 'Test Admin',
                'email' => 'admin@test.com',
                'role' => 'hospital_admin',
                'is_active' => true,
            ]);

            $user = User::first();
            $this->assertNotNull($user->password);
            $this->assertEquals(0, $user->token_version);
            $this->assertEquals(0, $user->failed_login_attempts);
        });
    }

    public function test_tenant_provisioning_dispatches_onboarding_email(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Hospital',
            'subdomain' => 'test-hospital',
            'status' => 'active',
        ]);

        $job = new TenantProvisioningJob(
            $tenant,
            'admin@test.com',
            'Test Admin'
        );

        $job->handle();

        // Verify onboarding notification was sent
        Notification::assertSentTimes(TenantOnboardingNotification::class, 1);
    }

    public function test_tenant_provisioning_marks_tenant_as_failed_on_error(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Hospital',
            'subdomain' => 'test-hospital',
            'status' => 'active',
        ]);

        // Create a Super Admin to receive failure notification
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => bcrypt('password'),
        ]);

        // Force an error by using an invalid tenant (simulate migration failure)
        // We'll mock the Artisan call to throw an exception
        $this->expectException(\Exception::class);

        try {
            // Create a job that will fail
            $job = new TenantProvisioningJob(
                $tenant,
                'admin@test.com',
                'Test Admin'
            );

            // Mock Artisan to throw exception
            \Illuminate\Support\Facades\Artisan::shouldReceive('call')
                ->andThrow(new \Exception('Migration failed'));

            $job->handle();
        } catch (\Exception $e) {
            // Verify tenant status was updated to failed
            $tenant->refresh();
            $this->assertEquals('failed', $tenant->status);

            // Verify Super Admin was notified
            Notification::assertSentTo(
                $superAdmin,
                TenantProvisioningFailedNotification::class
            );

            throw $e;
        }
    }

    public function test_tenant_provisioning_completes_within_60_seconds(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Hospital',
            'subdomain' => 'test-hospital',
            'status' => 'active',
        ]);

        $startTime = microtime(true);

        $job = new TenantProvisioningJob(
            $tenant,
            'admin@test.com',
            'Test Admin'
        );

        $job->handle();

        $duration = microtime(true) - $startTime;

        // Verify job completed within 60 seconds (requirement 14.3)
        $this->assertLessThan(60, $duration, 'Tenant provisioning should complete within 60 seconds');
    }

    public function test_tenant_provisioning_updates_status_to_active(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Hospital',
            'subdomain' => 'test-hospital',
            'status' => 'inactive',
        ]);

        $job = new TenantProvisioningJob(
            $tenant,
            'admin@test.com',
            'Test Admin'
        );

        $job->handle();

        // Verify tenant status was updated to active
        $tenant->refresh();
        $this->assertEquals('active', $tenant->status);
    }
}
