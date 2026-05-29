<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class SuperAdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected SuperAdmin $superAdmin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a super admin
        $this->superAdmin = SuperAdmin::create([
            'name' => 'Test Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
            'token_version' => 0,
        ]);

        // Generate JWT token
        $this->token = JWTAuth::fromUser($this->superAdmin);
    }

    public function test_can_list_hospitals(): void
    {
        // Create test tenants
        $plan = Plan::create([
            'name' => 'Basic Plan',
            'feature_flags' => ['sms_notifications' => true],
            'api_rate_limit' => 60,
        ]);

        Tenant::create([
            'name' => 'Test Hospital 1',
            'subdomain' => 'test1',
            'status' => 'active',
            'plan_id' => $plan->id,
        ]);

        Tenant::create([
            'name' => 'Test Hospital 2',
            'subdomain' => 'test2',
            'status' => 'suspended',
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/admin/hospitals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'subdomain', 'status', 'plan_id'],
                ],
                'meta' => ['pagination'],
            ]);
    }

    public function test_can_create_hospital(): void
    {
        $plan = Plan::create([
            'name' => 'Basic Plan',
            'feature_flags' => ['sms_notifications' => true],
            'api_rate_limit' => 60,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/admin/hospitals', [
            'name' => 'New Hospital',
            'subdomain' => 'newhospital',
            'plan_id' => $plan->id,
            'admin_name' => 'Hospital Admin',
            'admin_email' => 'admin@newhospital.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Hospital created successfully. Provisioning in progress.',
            ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'New Hospital',
            'subdomain' => 'newhospital',
            'status' => 'active',
        ]);
    }

    public function test_can_suspend_hospital(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Hospital',
            'subdomain' => 'test',
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->patchJson("/api/v1/admin/hospitals/{$tenant->id}/suspend");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Hospital suspended successfully. All user sessions have been invalidated.',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'suspended',
        ]);
    }

    public function test_can_activate_hospital(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Hospital',
            'subdomain' => 'test',
            'status' => 'suspended',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->patchJson("/api/v1/admin/hospitals/{$tenant->id}/activate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Hospital activated successfully.',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'active',
        ]);
    }

    public function test_can_update_system_settings(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/v1/admin/settings', [
            'mobile_app_version' => '1.0.0',
            'api_rate_limit' => 100,
            'maintenance_mode' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'System settings updated successfully.',
            ]);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'mobile_app_version',
            'value' => '1.0.0',
        ]);
    }

    public function test_can_get_feature_flags(): void
    {
        SystemSetting::set('feature_sms_notifications', '1', $this->superAdmin->id);
        SystemSetting::set('feature_lab_module', '0', $this->superAdmin->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/admin/feature-flags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_can_update_feature_flag(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/v1/admin/feature-flags/feature_sms_notifications', [
            'enabled' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Feature flag updated successfully.',
            ]);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'feature_sms_notifications',
            'value' => '1',
        ]);
    }

    public function test_unauthorized_access_without_token(): void
    {
        $response = $this->getJson('/api/v1/admin/hospitals');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                ],
            ]);
    }
}
