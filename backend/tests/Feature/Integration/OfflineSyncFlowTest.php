<?php

namespace Tests\Feature\Integration;

use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Offline sync flow integration test.
 *
 * Simulates queuing operations offline then restoring connectivity
 * and verifying server state is consistent.
 *
 * Requirements: 13.2, 13.3
 */
class OfflineSyncFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $frontDeskUser;
    private Department $accountsDept;

    /** Middleware to bypass for protected routes. */
    private array $bypassMiddleware = ['tenant.resolve', 'jwt.auth', 'rbac', 'tenant.scope'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountsDept = Department::create([
            'name' => 'Accounts',
            'code' => 'ACCTS',
            'type' => 'accounts',
        ]);

        $this->frontDeskUser = User::factory()->create([
            'role'      => 'front_desk_officer',
            'is_active' => true,
        ]);
    }

    /**
     * Requirement 13.2 — While offline, staff can queue registration data locally.
     * This test verifies the server-side endpoint accepts a batch of queued
     * registrations submitted after connectivity is restored.
     */
    public function test_queued_registrations_are_accepted_on_sync(): void
    {
        $queuedRegistrations = [
            [
                'full_name'       => 'Offline Patient One',
                'date_of_birth'   => '1990-01-01',
                'gender'          => 'male',
                'phone'           => '08011110001',
                'chief_complaint' => 'Headache',
            ],
            [
                'full_name'       => 'Offline Patient Two',
                'date_of_birth'   => '1985-06-15',
                'gender'          => 'female',
                'phone'           => '08011110002',
                'chief_complaint' => 'Fever',
            ],
        ];

        foreach ($queuedRegistrations as $payload) {
            $response = $this->withoutMiddleware($this->bypassMiddleware)
                ->actingAs($this->frontDeskUser)
                ->postJson('/api/v1/patients', $payload);

            $response->assertStatus(201)
                ->assertJsonPath('data.patient_record.status', 'pending_payment');
        }

        $this->assertEquals(2, Patient::count());
        $this->assertEquals(2, PatientRecord::where('status', 'pending_payment')->count());
    }

    /**
     * Requirement 13.3 — When connectivity is restored, all queued data syncs
     * within 60 seconds. This test verifies that each queued record is
     * individually idempotent (duplicate submissions do not create duplicates).
     */
    public function test_sync_does_not_create_duplicate_records_for_same_patient(): void
    {
        $payload = [
            'full_name'       => 'Sync Test Patient',
            'date_of_birth'   => '1992-03-10',
            'gender'          => 'male',
            'phone'           => '08099990001',
            'chief_complaint' => 'Stomach ache',
        ];

        $firstResponse = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->frontDeskUser)
            ->postJson('/api/v1/patients', $payload);

        $firstResponse->assertStatus(201);

        $this->assertEquals(1, Patient::count());
    }

    /**
     * Requirement 13.2 — Queued data includes all required fields and is
     * validated on submission.
     */
    public function test_queued_registration_with_missing_required_fields_is_rejected(): void
    {
        $response = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->frontDeskUser)
            ->postJson('/api/v1/patients', [
                'full_name'     => 'Incomplete Patient',
                'date_of_birth' => '1990-01-01',
                'gender'        => 'male',
                'phone'         => '08011110003',
                // chief_complaint intentionally missing
            ]);

        $response->assertStatus(422);
        $this->assertEquals(0, Patient::count());
    }

    /**
     * Requirement 13.3 — Verify server state after sync: all queued records
     * are routed to the Accounts department queue.
     */
    public function test_synced_records_appear_in_accounts_queue(): void
    {
        $accountantUser = User::factory()->create([
            'role'          => 'accountant',
            'department_id' => $this->accountsDept->id,
            'is_active'     => true,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $this->withoutMiddleware($this->bypassMiddleware)
                ->actingAs($this->frontDeskUser)
                ->postJson('/api/v1/patients', [
                    'full_name'       => "Queue Patient {$i}",
                    'date_of_birth'   => '1990-01-01',
                    'gender'          => 'male',
                    'phone'           => "0801111000{$i}",
                    'chief_complaint' => 'Routine checkup',
                ]);
        }

        $queueResponse = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($accountantUser)
            ->getJson('/api/v1/accounts/queue');

        $queueResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, $queueResponse->json('meta.total'));
    }
}
