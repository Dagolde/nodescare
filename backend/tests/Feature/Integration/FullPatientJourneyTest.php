<?php

namespace Tests\Feature\Integration;

use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientJourneyEvent;
use App\Models\PatientRecord;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full patient journey integration test.
 *
 * Covers: Registration → Accounts (invoice + payment) → Triage → Doctor
 *         → Pharmacy → Discharge
 *
 * Requirements: All workflow requirements (5, 6, 7, 8, 16)
 */
class FullPatientJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Department $accountsDept;
    private Department $triageDept;
    private Department $doctorDept;
    private Department $pharmacyDept;
    private WorkflowTemplate $template;

    private User $frontDeskUser;
    private User $accountantUser;
    private User $triageUser;
    private User $doctorUser;
    private User $pharmacistUser;

    /** Middleware aliases to bypass for protected routes in tests. */
    private array $bypassMiddleware = ['tenant.resolve', 'jwt.auth', 'rbac', 'tenant.scope'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDepartmentsAndWorkflow();
        $this->seedStaffUsers();
    }

    private function seedDepartmentsAndWorkflow(): void
    {
        $this->accountsDept = Department::create([
            'name' => 'Accounts',
            'code' => 'ACCTS',
            'type' => 'accounts',
        ]);

        $this->triageDept = Department::create([
            'name' => 'Triage',
            'code' => 'TRIAGE',
            'type' => 'triage',
        ]);

        $this->doctorDept = Department::create([
            'name' => 'Doctor',
            'code' => 'DOC',
            'type' => 'clinical',
        ]);

        $this->pharmacyDept = Department::create([
            'name' => 'Pharmacy',
            'code' => 'PHARM',
            'type' => 'pharmacy',
        ]);

        $this->template = WorkflowTemplate::create([
            'name'        => 'Standard Journey',
            'description' => 'Test workflow',
            'is_default'  => true,
            'created_by'  => null,
        ]);

        $steps = [
            ['department_id' => $this->triageDept->id, 'step_order' => 1, 'is_terminal' => false],
            ['department_id' => $this->doctorDept->id, 'step_order' => 2, 'is_terminal' => false],
            ['department_id' => $this->pharmacyDept->id, 'step_order' => 3, 'is_terminal' => true],
        ];

        foreach ($steps as $step) {
            WorkflowTemplateStep::create(array_merge($step, ['template_id' => $this->template->id]));
        }
    }

    private function seedStaffUsers(): void
    {
        $this->frontDeskUser = User::factory()->create([
            'role'          => 'front_desk_officer',
            'department_id' => null,
            'is_active'     => true,
        ]);

        $this->accountantUser = User::factory()->create([
            'role'          => 'accountant',
            'department_id' => $this->accountsDept->id,
            'is_active'     => true,
        ]);

        $this->triageUser = User::factory()->create([
            'role'          => 'department_staff',
            'department_id' => $this->triageDept->id,
            'is_active'     => true,
        ]);

        $this->doctorUser = User::factory()->create([
            'role'          => 'doctor',
            'department_id' => $this->doctorDept->id,
            'is_active'     => true,
        ]);

        $this->pharmacistUser = User::factory()->create([
            'role'          => 'pharmacist',
            'department_id' => $this->pharmacyDept->id,
            'is_active'     => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Step 1: Patient Registration
    // -------------------------------------------------------------------------

    public function test_step1_patient_registration_creates_record_with_pending_payment(): void
    {
        $response = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->frontDeskUser)
            ->postJson('/api/v1/patients', [
                'full_name'       => 'John Doe',
                'date_of_birth'   => '1990-05-15',
                'gender'          => 'male',
                'phone'           => '08012345678',
                'chief_complaint' => 'Fever and headache',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.patient_record.status', 'pending_payment')
            ->assertJsonPath('data.patient_record.current_department_id', $this->accountsDept->id);

        $patientRecordId = $response->json('data.patient_record.id');
        $this->assertDatabaseHas('patient_journey_events', [
            'patient_record_id' => $patientRecordId,
            'event_type'        => 'registered',
        ]);
    }

    // -------------------------------------------------------------------------
    // Step 2: Accounts — Invoice creation + payment + settlement
    // -------------------------------------------------------------------------

    public function test_step2_accounts_invoice_payment_and_settlement_routes_to_triage(): void
    {
        $patient = Patient::create([
            'patient_number'  => 'P-20240101-0001',
            'full_name'       => 'Jane Smith',
            'date_of_birth'   => '1985-03-20',
            'gender'          => 'female',
            'phone'           => '08098765432',
            'chief_complaint' => 'Chest pain',
            'created_by'      => $this->frontDeskUser->id,
        ]);

        $patientRecord = PatientRecord::create([
            'patient_id'            => $patient->id,
            'workflow_template_id'  => $this->template->id,
            'status'                => 'pending_payment',
            'current_department_id' => $this->accountsDept->id,
            'registered_by'         => $this->frontDeskUser->id,
        ]);

        $invoiceResponse = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->accountantUser)
            ->postJson("/api/v1/patient-records/{$patientRecord->id}/invoices", [
                'is_new_patient' => true,
                'items' => [
                    ['service_name' => 'Registration/Card Fee', 'unit_price' => 500, 'quantity' => 1],
                    ['service_name' => 'Consultation Fee', 'unit_price' => 2000, 'quantity' => 1],
                ],
            ]);

        $invoiceResponse->assertStatus(201);
        $invoiceId = $invoiceResponse->json('data.id');

        $paymentResponse = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->accountantUser)
            ->postJson("/api/v1/invoices/{$invoiceId}/payments", [
                'payment_type'      => 'cash',
                'amount'            => 2500,
                'receipt_reference' => 'RCP-001',
            ]);

        $paymentResponse->assertStatus(201);

        $settleResponse = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->accountantUser)
            ->postJson("/api/v1/invoices/{$invoiceId}/settle");

        $settleResponse->assertStatus(200);

        $patientRecord->refresh();
        $this->assertEquals('active', $patientRecord->status);
        $this->assertEquals($this->triageDept->id, $patientRecord->current_department_id);

        $this->assertDatabaseHas('patient_journey_events', [
            'patient_record_id' => $patientRecord->id,
            'event_type'        => 'routed',
            'to_department_id'  => $this->triageDept->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Step 3: Triage → Doctor routing
    // -------------------------------------------------------------------------

    public function test_step3_triage_routes_patient_to_doctor(): void
    {
        $patient = Patient::create([
            'patient_number'  => 'P-20240101-0002',
            'full_name'       => 'Bob Jones',
            'date_of_birth'   => '1970-07-10',
            'gender'          => 'male',
            'phone'           => '08011112222',
            'chief_complaint' => 'Back pain',
            'created_by'      => $this->frontDeskUser->id,
        ]);

        $patientRecord = PatientRecord::create([
            'patient_id'            => $patient->id,
            'workflow_template_id'  => $this->template->id,
            'status'                => 'active',
            'current_department_id' => $this->triageDept->id,
            'current_step_order'    => 1,
            'registered_by'         => $this->frontDeskUser->id,
        ]);

        $response = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->triageUser)
            ->postJson("/api/v1/patient-records/{$patientRecord->id}/complete", [
                'notes' => 'Vitals taken. BP 120/80. Temp 37.2°C.',
            ]);

        $response->assertStatus(200);

        $patientRecord->refresh();
        $this->assertEquals($this->doctorDept->id, $patientRecord->current_department_id);
        $this->assertEquals(2, $patientRecord->current_step_order);

        $this->assertDatabaseHas('patient_journey_events', [
            'patient_record_id'  => $patientRecord->id,
            'from_department_id' => $this->triageDept->id,
            'to_department_id'   => $this->doctorDept->id,
            'event_type'         => 'routed',
        ]);
    }

    // -------------------------------------------------------------------------
    // Step 4: Doctor → Pharmacy routing
    // -------------------------------------------------------------------------

    public function test_step4_doctor_routes_patient_to_pharmacy(): void
    {
        $patient = Patient::create([
            'patient_number'  => 'P-20240101-0003',
            'full_name'       => 'Alice Brown',
            'date_of_birth'   => '1995-11-25',
            'gender'          => 'female',
            'phone'           => '08033334444',
            'chief_complaint' => 'Cough',
            'created_by'      => $this->frontDeskUser->id,
        ]);

        $patientRecord = PatientRecord::create([
            'patient_id'            => $patient->id,
            'workflow_template_id'  => $this->template->id,
            'status'                => 'active',
            'current_department_id' => $this->doctorDept->id,
            'current_step_order'    => 2,
            'registered_by'         => $this->frontDeskUser->id,
        ]);

        $response = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->doctorUser)
            ->postJson("/api/v1/patient-records/{$patientRecord->id}/complete", [
                'notes' => 'Diagnosed with upper respiratory infection.',
            ]);

        $response->assertStatus(200);

        $patientRecord->refresh();
        $this->assertEquals($this->pharmacyDept->id, $patientRecord->current_department_id);
        $this->assertEquals(3, $patientRecord->current_step_order);
    }

    // -------------------------------------------------------------------------
    // Step 5: Pharmacy → Discharge
    // -------------------------------------------------------------------------

    public function test_step5_pharmacy_dispenses_and_patient_is_discharged(): void
    {
        $patient = Patient::create([
            'patient_number'  => 'P-20240101-0004',
            'full_name'       => 'Carol White',
            'date_of_birth'   => '2000-01-01',
            'gender'          => 'female',
            'phone'           => '08055556666',
            'chief_complaint' => 'Malaria symptoms',
            'created_by'      => $this->frontDeskUser->id,
        ]);

        $patientRecord = PatientRecord::create([
            'patient_id'            => $patient->id,
            'workflow_template_id'  => $this->template->id,
            'status'                => 'active',
            'current_department_id' => $this->pharmacyDept->id,
            'current_step_order'    => 3,
            'registered_by'         => $this->frontDeskUser->id,
        ]);

        $dispensingResponse = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->pharmacistUser)
            ->postJson("/api/v1/patient-records/{$patientRecord->id}/dispensing", [
                'drug_name'    => 'Artemether-Lumefantrine',
                'dosage'       => '80/480mg',
                'quantity'     => '6 tablets',
                'dispensed_at' => now()->toDateTimeString(),
            ]);

        $dispensingResponse->assertStatus(201);

        $routeResponse = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->pharmacistUser)
            ->postJson("/api/v1/patient-records/{$patientRecord->id}/complete", [
                'notes' => 'Medications dispensed. Patient discharged.',
            ]);

        $routeResponse->assertStatus(200);

        $patientRecord->refresh();
        $this->assertEquals('completed', $patientRecord->status);
        $this->assertNotNull($patientRecord->discharged_at);

        $this->assertDatabaseHas('patient_journey_events', [
            'patient_record_id' => $patientRecord->id,
            'event_type'        => 'discharged',
        ]);
    }

    // -------------------------------------------------------------------------
    // Full end-to-end journey status transitions
    // -------------------------------------------------------------------------

    public function test_full_journey_status_transitions_are_recorded(): void
    {
        $registerResponse = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->frontDeskUser)
            ->postJson('/api/v1/patients', [
                'full_name'       => 'Test Patient',
                'date_of_birth'   => '1988-06-15',
                'gender'          => 'male',
                'phone'           => '08077778888',
                'chief_complaint' => 'General checkup',
            ]);

        $registerResponse->assertStatus(201);
        $patientRecordId = $registerResponse->json('data.patient_record.id');

        $this->assertDatabaseHas('patient_records', [
            'id'     => $patientRecordId,
            'status' => 'pending_payment',
        ]);

        $eventCount = PatientJourneyEvent::where('patient_record_id', $patientRecordId)->count();
        $this->assertGreaterThanOrEqual(1, $eventCount);
    }
}
