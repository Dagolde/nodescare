<?php

namespace Tests\Feature\Integration;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Hospital suspension integration test.
 *
 * Verifies that suspending a tenant immediately invalidates all active
 * user tokens by incrementing token_version for every user in the tenant.
 *
 * Requirements: 2.3
 */
class HospitalSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = SuperAdmin::create([
            'name'     => 'Super Admin',
            'email'    => 'super@hhms.app',
            'password' => Hash::make('SuperSecret123!'),
        ]);
    }

    /**
     * Requirement 2.3 — When the Super_Admin suspends a hospital, the HHMS SHALL
     * immediately revoke all active sessions for that hospital's users.
     *
     * Token revocation is implemented by incrementing token_version for all users.
     * The JWT middleware rejects tokens whose embedded token_version does not match
     * the current user token_version.
     */
    public function test_suspending_hospital_increments_token_version_for_all_users(): void
    {
        $tenant = Tenant::create([
            'id'        => \Illuminate\Support\Str::uuid(),
            'name'      => 'Test Hospital',
            'subdomain' => 'test-hospital',
            'status'    => 'active',
        ]);

        // Create users inside the tenant context
        $initialTokenVersion = 0;
        $users = [];

        $tenant->run(function () use (&$users, $initialTokenVersion) {
            for ($i = 0; $i < 3; $i++) {
                $users[] = User::factory()->create([
                    'token_version' => $initialTokenVersion,
                    'is_active'     => true,
                ]);
            }
        });

        // Suspend the hospital
        $response = $this->withoutMiddleware(\App\Http\Middleware\SuperAdminAuthMiddleware::class)
            ->actingAs($this->superAdmin)
            ->patchJson("/api/v1/admin/hospitals/{$tenant->id}/suspend");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify tenant status is suspended
        $tenant->refresh();
        $this->assertEquals('suspended', $tenant->status);

        // Verify all users have incremented token_version
        $tenant->run(function () use ($initialTokenVersion) {
            $users = User::all();
            foreach ($users as $user) {
                $this->assertGreaterThan(
                    $initialTokenVersion,
                    $user->token_version,
                    "User {$user->id} token_version was not incremented on suspension."
                );
            }
        });
    }

    /**
     * Requirement 2.3 — After suspension, new login attempts are rejected.
     */
    public function test_login_is_rejected_after_hospital_suspension(): void
    {
        $tenant = Tenant::create([
            'id'        => \Illuminate\Support\Str::uuid(),
            'name'      => 'Suspend Login Test Hospital',
            'subdomain' => 'suspend-login-test',
            'status'    => 'active',
        ]);

        $userEmail    = 'staff@suspendtest.com';
        $userPassword = 'Password123!';

        $tenant->run(function () use ($userEmail, $userPassword) {
            User::factory()->create([
                'email'    => $userEmail,
                'password' => Hash::make($userPassword),
                'is_active' => true,
            ]);
        });

        // Suspend the hospital
        $this->withoutMiddleware(\App\Http\Middleware\SuperAdminAuthMiddleware::class)
            ->actingAs($this->superAdmin)
            ->patchJson("/api/v1/admin/hospitals/{$tenant->id}/suspend")
            ->assertStatus(200);

        // Attempt login — should be rejected because tenant is suspended
        // The TenantResolutionMiddleware or JWTAuthMiddleware should block this
        $loginResponse = $this->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->postJson('/api/v1/auth/login', [
                'email'    => $userEmail,
                'password' => $userPassword,
            ]);

        // Expect 403 (suspended) or 401 (tenant not found/inactive)
        $this->assertContains($loginResponse->status(), [401, 403]);
    }

    /**
     * Requirement 2.3 — Suspending an already-suspended hospital returns an error.
     */
    public function test_suspending_already_suspended_hospital_returns_error(): void
    {
        $tenant = Tenant::create([
            'id'        => \Illuminate\Support\Str::uuid(),
            'name'      => 'Already Suspended Hospital',
            'subdomain' => 'already-suspended',
            'status'    => 'suspended',
        ]);

        $response = $this->withoutMiddleware(\App\Http\Middleware\SuperAdminAuthMiddleware::class)
            ->actingAs($this->superAdmin)
            ->patchJson("/api/v1/admin/hospitals/{$tenant->id}/suspend");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ALREADY_SUSPENDED');
    }

    /**
     * Requirement 2.3 — Token version is incremented atomically for all users.
     */
    public function test_token_version_incremented_atomically_for_all_users(): void
    {
        $tenant = Tenant::create([
            'id'        => \Illuminate\Support\Str::uuid(),
            'name'      => 'Atomic Test Hospital',
            'subdomain' => 'atomic-test',
            'status'    => 'active',
        ]);

        $tenant->run(function () {
            User::factory()->count(5)->create(['token_version' => 2]);
        });

        $this->withoutMiddleware(\App\Http\Middleware\SuperAdminAuthMiddleware::class)
            ->actingAs($this->superAdmin)
            ->patchJson("/api/v1/admin/hospitals/{$tenant->id}/suspend")
            ->assertStatus(200);

        $tenant->run(function () {
            // All users should have token_version = 3 (incremented from 2)
            $this->assertEquals(0, User::where('token_version', '<', 3)->count());
        });
    }
}
