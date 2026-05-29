<?php

namespace Tests\Feature\Patient;

use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run tenant migrations
        $this->artisan('tenants:migrate');
    }

    /** @test */
    public function it_can_register_a_new_patient_with_required_fields()
    {
        // Create Accounts department
        $accountsDepartment = Department::create([
            'name' => 'Accounts',
            'code' => 'ACCTS',
            'type' => 'accounts',
            'is_active' => true,
        ]);

        // Create a front desk officer
        $frontDeskOfficer = User::factory()->create([
            'role' => 'front_desk_officer',
        ]);

        // Patient registration data
        $patientData = [
            'full_name' => 'John Doe',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'phone' => '+1234567890',
            'chief_complaint' => 'Fever and headache',
        ];

        // Act as front desk officer and register patient
        $response = $this->actingAs($frontDeskOfficer)
            ->postJson('/api/v1/patients', $patientData);

        // Assert response
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Patient registered successfully',
            ]);

        // Assert patient was created
        $this->assertDatabaseHas('patients', [
            'gender' => 'male',
            'chief_complaint' => 'Fever and headache',
            'created_by' => $frontDeskOfficer->id,
        ]);

        // Assert patient record was created with pending_payment status
        $this->assertDatabaseHas('patient_records', [
            'status' => 'pending_payment',
            'current_department_id' => $accountsDepartment->id,
            'registered_by' => $frontDeskOfficer->id,
        ]);

        // Assert patient number format (P-YYYYMMDD-XXXX)
        $patient = Patient::first();
        $this->assertMatchesRegularExpression('/^P-\d{8}-\d{4}$/', $patient->patient_number);
    }

    /** @test */
    public function it_validates_required_fields_during_registration()
    {
        $frontDeskOfficer = User::factory()->create([
            'role' => 'front_desk_officer',
        ]);

        // Missing required fields
        $response = $this->actingAs($frontDeskOfficer)
            ->postJson('/api/v1/patients', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['full_name', 'date_of_birth', 'gender', 'phone', 'chief_complaint']);
    }

    /** @test */
    public function it_generates_unique_patient_numbers()
    {
        $accountsDepartment = Department::create([
            'name' => 'Accounts',
            'code' => 'ACCTS',
            'type' => 'accounts',
            'is_active' => true,
        ]);

        $frontDeskOfficer = User::factory()->create([
            'role' => 'front_desk_officer',
        ]);

        $patientData = [
            'full_name' => 'John Doe',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'phone' => '+1234567890',
            'chief_complaint' => 'Fever',
        ];

        // Register first patient
        $this->actingAs($frontDeskOfficer)
            ->postJson('/api/v1/patients', $patientData);

        // Register second patient
        $patientData['full_name'] = 'Jane Doe';
        $this->actingAs($frontDeskOfficer)
            ->postJson('/api/v1/patients', $patientData);

        // Assert both patients have unique patient numbers
        $patients = Patient::all();
        $this->assertCount(2, $patients);
        $this->assertNotEquals($patients[0]->patient_number, $patients[1]->patient_number);
    }

    /** @test */
    public function it_encrypts_pii_fields()
    {
        $accountsDepartment = Department::create([
            'name' => 'Accounts',
            'code' => 'ACCTS',
            'type' => 'accounts',
            'is_active' => true,
        ]);

        $frontDeskOfficer = User::factory()->create([
            'role' => 'front_desk_officer',
        ]);

        $patientData = [
            'full_name' => 'John Doe',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'phone' => '+1234567890',
            'address' => '123 Main St',
            'chief_complaint' => 'Fever',
        ];

        $this->actingAs($frontDeskOfficer)
            ->postJson('/api/v1/patients', $patientData);

        $patient = Patient::first();

        // Assert PII fields are encrypted in database (raw values should not match)
        $this->assertNotEquals('John Doe', $patient->getAttributes()['full_name']);
        $this->assertNotEquals('1990-01-15', $patient->getAttributes()['date_of_birth']);
        $this->assertNotEquals('+1234567890', $patient->getAttributes()['phone']);
        $this->assertNotEquals('123 Main St', $patient->getAttributes()['address']);

        // Assert decrypted values match original
        $this->assertEquals('John Doe', $patient->full_name);
        $this->assertEquals('1990-01-15', $patient->date_of_birth);
        $this->assertEquals('+1234567890', $patient->phone);
        $this->assertEquals('123 Main St', $patient->address);
    }
}
