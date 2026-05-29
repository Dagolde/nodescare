<?php

namespace Tests\Feature\Patient;

use App\Models\Department;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the PatientController tenant ownership guard.
 *
 * Validates Requirements 1.1–1.8: every patient endpoint must verify
 * that the requested patient belongs to the authenticated user's tenant,
 * returning 404 NOT_FOUND for both missing patients and cross-tenant patients.
 */
class PatientTenantGuardTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantA;
    private string $tenantB;
    private User $userA;
    private Patient $patientA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = (string) Str::uuid();
        $this->tenantB = (string) Str::uuid();

        // Insert minimal tenant rows so FK constraints pass on SQLite
        \Illuminate\Support\Facades\DB::table('tenants')->insert([
            ['id' => $this->tenantA, 'name' => 'Hospital A', 'subdomain' => 'hospital-a', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $this->tenantB, 'name' => 'Hospital B', 'subdomain' => 'hospital-b', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Bind tenant A as the current tenant
        app()->instance('current_tenant_id', $this->tenantA);

        // Create a user belonging to tenant A
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA,
            'role'      => 'hospital_admin',
        ]);

        // Bypass JWT and tenant verification middleware — we test the controller guard only
        $this->withoutMiddleware([
            \App\Http\Middleware\JWTAuthMiddleware::class,
            \App\Http\Middleware\TenantVerificationMiddleware::class,
            \App\Http\Middleware\RBACMiddleware::class,
            \App\Http\Middleware\TenantScopeMiddleware::class,
            \App\Http\Middleware\MaintenanceModeMiddleware::class,
        ]);
        // Create a patient belonging to tenant A (bypass TenantScope for direct creation)
        $this->patientA = Patient::withoutGlobalScopes()->create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => $this->tenantA,
            'patient_number' => 'P-20240101-0001',
            'full_name'      => 'Alice Smith',
            'date_of_birth'  => '1985-06-15',
            'gender'         => 'female',
            'phone'          => '+1111111111',
            'chief_complaint' => 'Headache',
            'is_anonymised'  => false,
            'created_by'     => $this->userA->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper: create a patient belonging to tenant B (cross-tenant)
    // -------------------------------------------------------------------------

    private function createCrossTenantPatient(): Patient
    {
        return Patient::withoutGlobalScopes()->create([
            'id'             => (string) Str::uuid(),
            'tenant_id'      => $this->tenantB,
            'patient_number' => 'P-20240101-0002',
            'full_name'      => 'Bob Jones',
            'date_of_birth'  => '1990-03-20',
            'gender'         => 'male',
            'phone'          => '+2222222222',
            'chief_complaint' => 'Back pain',
            'is_anonymised'  => false,
            'created_by'     => $this->userA->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper: actingAs with X-Tenant-ID header pre-set
    // -------------------------------------------------------------------------

    private function asUserA(): \Illuminate\Testing\TestResponse
    {
        // Returns a fluent test instance with the tenant header bound.
        // Usage: $this->asUserA()->getJson(...)
        return $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA]);
    }

    private function withTenant(): self
    {
        $this->withHeaders(['X-Tenant-ID' => $this->tenantA]);
        return $this;
    }

    // =========================================================================
    // show() — GET /api/v1/patients/{id}
    // =========================================================================

    /** @test */
    public function show_returns_404_for_non_existent_patient(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->getJson('/api/v1/patients/' . Str::uuid());

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    /** @test */
    public function show_returns_404_for_cross_tenant_patient(): void
    {
        $crossTenantPatient = $this->createCrossTenantPatient();

        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->getJson('/api/v1/patients/' . $crossTenantPatient->id);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    /** @test */
    public function show_returns_patient_data_for_same_tenant(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->getJson('/api/v1/patients/' . $this->patientA->id);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['id' => $this->patientA->id]]);
    }

    // =========================================================================
    // journey() — GET /api/v1/patients/{id}/journey
    // =========================================================================

    /** @test */
    public function journey_returns_404_for_non_existent_patient(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->getJson('/api/v1/patients/' . Str::uuid() . '/journey');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    /** @test */
    public function journey_returns_404_for_cross_tenant_patient(): void
    {
        $crossTenantPatient = $this->createCrossTenantPatient();

        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->getJson('/api/v1/patients/' . $crossTenantPatient->id . '/journey');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    // =========================================================================
    // attachDocument() — POST /api/v1/patients/{id}/documents
    // =========================================================================

    /** @test */
    public function attach_document_returns_404_for_non_existent_patient(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->postJson('/api/v1/patients/' . Str::uuid() . '/documents', [
                'patient_record_id' => (string) Str::uuid(),
                'document_type'     => 'lab_result',
                'object_key'        => 'uploads/test.pdf',
                'original_filename' => 'test.pdf',
                'file_size_bytes'   => 1024,
            ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    /** @test */
    public function attach_document_returns_404_for_cross_tenant_patient(): void
    {
        $crossTenantPatient = $this->createCrossTenantPatient();

        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->postJson('/api/v1/patients/' . $crossTenantPatient->id . '/documents', [
                'patient_record_id' => (string) Str::uuid(),
                'document_type'     => 'lab_result',
                'object_key'        => 'uploads/test.pdf',
                'original_filename' => 'test.pdf',
                'file_size_bytes'   => 1024,
            ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    // =========================================================================
    // getDocumentUrl() — GET /api/v1/patients/{id}/documents/{docId}/url
    // =========================================================================

    /** @test */
    public function get_document_url_returns_404_for_non_existent_patient(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->getJson('/api/v1/patients/' . Str::uuid() . '/documents/' . Str::uuid() . '/url');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    /** @test */
    public function get_document_url_returns_404_for_cross_tenant_patient(): void
    {
        $crossTenantPatient = $this->createCrossTenantPatient();

        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->getJson('/api/v1/patients/' . $crossTenantPatient->id . '/documents/' . Str::uuid() . '/url');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    // =========================================================================
    // getPhotoUrl() — GET /api/v1/patients/{id}/photo/url
    // =========================================================================

    /** @test */
    public function get_photo_url_returns_404_for_non_existent_patient(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->getJson('/api/v1/patients/' . Str::uuid() . '/photo/url');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    /** @test */
    public function get_photo_url_returns_404_for_cross_tenant_patient(): void
    {
        $crossTenantPatient = $this->createCrossTenantPatient();

        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->getJson('/api/v1/patients/' . $crossTenantPatient->id . '/photo/url');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    // =========================================================================
    // anonymise() — POST /api/v1/patients/{id}/anonymise
    // =========================================================================

    /** @test */
    public function anonymise_returns_404_for_non_existent_patient(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->postJson('/api/v1/patients/' . Str::uuid() . '/anonymise');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    /** @test */
    public function anonymise_returns_404_for_cross_tenant_patient(): void
    {
        $crossTenantPatient = $this->createCrossTenantPatient();

        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->postJson('/api/v1/patients/' . $crossTenantPatient->id . '/anonymise');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    // =========================================================================
    // newVisit() — POST /api/v1/patients/{id}/new-visit
    // =========================================================================

    /** @test */
    public function new_visit_returns_404_for_non_existent_patient(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->postJson('/api/v1/patients/' . Str::uuid() . '/new-visit', [
                'chief_complaint' => 'Follow-up visit',
            ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }

    /** @test */
    public function new_visit_returns_404_for_cross_tenant_patient(): void
    {
        $crossTenantPatient = $this->createCrossTenantPatient();

        $response = $this->actingAs($this->userA)
            ->withHeaders(['X-Tenant-ID' => $this->tenantA])
            ->postJson('/api/v1/patients/' . $crossTenantPatient->id . '/new-visit', [
                'chief_complaint' => 'Follow-up visit',
            ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Patient not found']]);
    }
}
