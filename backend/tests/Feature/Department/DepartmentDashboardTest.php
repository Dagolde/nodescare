<?php

namespace Tests\Feature\Department;

use App\Models\Department;
use App\Models\PatientRecord;
use App\Models\PatientVitals;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepartmentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Department $department;
    protected User $hod;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::create([
            'id' => Str::uuid(),
            'name' => 'Test Hospital',
            'subdomain' => 'test',
            'database_name' => 'test_db',
            'plan_id' => Str::uuid(),
            'is_active' => true,
        ]);

        // Set current tenant
        app()->instance('current_tenant_id', $this->tenant->id);

        // Create department
        $this->department = Department::create([
            'id' => Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Cardiology',
            'type' => 'clinical',
            'code' => 'CARD',
            'is_active' => true,
        ]);

        // Create HOD user
        $this->hod = User::create([
            'id' => Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Dr. HOD',
            'email' => 'hod@test.com',
            'phone' => '1234567890',
            'password' => Hash::make('password'),
            'role' => 'doctor',
            'is_active' => true,
            'is_hod' => true,
            'token_version' => 0,
        ]);
    }

    public function test_hod_can_get_dashboard_statistics(): void
    {
        // Create some patient records in the department
        PatientRecord::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'current_department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->hod, 'web')
            ->getJson('/department/api/dashboard/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_in_queue',
                    'patients_seen_today',
                    'average_wait_time',
                    'staff_on_duty',
                ],
            ]);
    }

    public function test_non_hod_cannot_access_dashboard_statistics(): void
    {
        $regularStaff = User::create([
            'id' => Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'name' => 'Regular Staff',
            'email' => 'staff@test.com',
            'phone' => '1234567891',
            'password' => Hash::make('password'),
            'role' => 'nurse',
            'is_active' => true,
            'is_hod' => false,
            'token_version' => 0,
        ]);

        $response = $this->actingAs($regularStaff, 'web')
            ->getJson('/department/api/dashboard/statistics');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_hod_can_record_vitals_for_patient_in_department(): void
    {
        $patientRecord = PatientRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $vitalsData = [
            'blood_pressure' => '120/80',
            'temperature' => 36.5,
            'pulse_rate' => 72,
            'respiratory_rate' => 16,
            'oxygen_saturation' => 98,
            'weight' => 70,
            'height' => 175,
        ];

        $response = $this->actingAs($this->hod, 'web')
            ->postJson("/department/api/patient-records/{$patientRecord->id}/vitals", $vitalsData);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Vitals recorded successfully.');

        // Verify vitals were saved
        $this->assertDatabaseHas('patient_vitals', [
            'patient_record_id' => $patientRecord->id,
            'recorded_by' => $this->hod->id,
            'temperature' => 36.5,
        ]);

        // Verify BMI was calculated
        $vitals = PatientVitals::latest()->first();
        $this->assertNotNull($vitals->bmi);
        $this->assertEqualsWithDelta(22.86, $vitals->bmi, 0.01);
    }

    public function test_hod_cannot_record_vitals_for_patient_in_other_department(): void
    {
        $otherDepartment = Department::create([
            'id' => Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Neurology',
            'type' => 'clinical',
            'code' => 'NEUR',
            'is_active' => true,
        ]);

        $patientRecord = PatientRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_department_id' => $otherDepartment->id,
            'status' => 'active',
        ]);

        $vitalsData = [
            'blood_pressure' => '120/80',
            'temperature' => 36.5,
        ];

        $response = $this->actingAs($this->hod, 'web')
            ->postJson("/department/api/patient-records/{$patientRecord->id}/vitals", $vitalsData);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'PATIENT_NOT_IN_DEPARTMENT');
    }

    public function test_hod_can_get_vitals_history(): void
    {
        $patientRecord = PatientRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_department_id' => $this->department->id,
            'status' => 'active',
        ]);

        // Create some vitals records
        PatientVitals::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'patient_record_id' => $patientRecord->id,
            'recorded_by' => $this->hod->id,
        ]);

        $response = $this->actingAs($this->hod, 'web')
            ->getJson("/department/api/patient-records/{$patientRecord->id}/vitals");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_validates_vitals_numeric_ranges(): void
    {
        $patientRecord = PatientRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_department_id' => $this->department->id,
            'status' => 'active',
        ]);

        $invalidVitalsData = [
            'temperature' => 50, // Invalid - too high
        ];

        $response = $this->actingAs($this->hod, 'web')
            ->postJson("/department/api/patient-records/{$patientRecord->id}/vitals", $invalidVitalsData);

        $response->assertStatus(422);
    }
}
