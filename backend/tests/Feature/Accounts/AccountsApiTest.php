<?php

namespace Tests\Feature\Accounts;

use App\Models\Department;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;
    protected Department $accountsDept;
    protected Department $clinicalDept;
    protected Patient $patient;
    protected PatientRecord $patientRecord;
    protected WorkflowTemplate $workflowTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create departments
        $this->accountsDept = Department::create([
            'name' => 'Accounts',
            'code' => 'ACCTS',
            'type' => 'accounts',
            'is_active' => true,
        ]);

        $this->clinicalDept = Department::create([
            'name' => 'Triage',
            'code' => 'TRIAGE',
            'type' => 'triage',
            'is_active' => true,
        ]);

        // Create accountant user
        $this->accountant = User::create([
            'name' => 'Test Accountant',
            'email' => 'accountant@test.com',
            'phone' => '1234567890',
            'password' => bcrypt('password'),
            'role' => 'accountant',
            'department_id' => $this->accountsDept->id,
            'is_active' => true,
        ]);

        // Create workflow template
        $this->workflowTemplate = WorkflowTemplate::create([
            'name' => 'Standard Workflow',
            'description' => 'Standard patient workflow',
            'is_default' => true,
            'created_by' => $this->accountant->id,
        ]);

        WorkflowTemplateStep::create([
            'template_id' => $this->workflowTemplate->id,
            'department_id' => $this->clinicalDept->id,
            'step_order' => 1,
            'is_terminal' => false,
        ]);

        // Create patient
        $this->patient = Patient::create([
            'patient_number' => 'HOS-2024-00001',
            'full_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '9876543210',
            'address' => '123 Test Street',
            'chief_complaint' => 'Test complaint',
            'created_by' => $this->accountant->id,
        ]);

        // Create patient record with pending_payment status
        $this->patientRecord = PatientRecord::create([
            'patient_id' => $this->patient->id,
            'workflow_template_id' => $this->workflowTemplate->id,
            'status' => 'pending_payment',
            'registered_by' => $this->accountant->id,
        ]);
    }

    public function test_get_accounts_queue_returns_pending_payment_records()
    {
        $response = $this->actingAs($this->accountant, 'api')
            ->getJson('/api/v1/accounts/queue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'patient_id',
                        'status',
                        'patient',
                    ],
                ],
                'meta' => ['total'],
            ])
            ->assertJson([
                'success' => true,
                'meta' => ['total' => 1],
            ]);
    }

    public function test_create_invoice_for_new_patient_requires_registration_and_consultation()
    {
        $response = $this->actingAs($this->accountant, 'api')
            ->postJson("/api/v1/patient-records/{$this->patientRecord->id}/invoices", [
                'is_new_patient' => true,
                'items' => [
                    [
                        'service_name' => 'Registration Fee',
                        'unit_price' => 1000,
                        'quantity' => 1,
                    ],
                    [
                        'service_name' => 'Consultation Fee',
                        'unit_price' => 2000,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'invoice_number',
                    'is_new_patient',
                    'total_amount',
                    'items',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_new_patient' => true,
                    'total_amount' => '3000.00',
                    'status' => 'issued',
                ],
            ]);
    }

    public function test_create_invoice_for_new_patient_fails_without_registration_fee()
    {
        $response = $this->actingAs($this->accountant, 'api')
            ->postJson("/api/v1/patient-records/{$this->patientRecord->id}/invoices", [
                'is_new_patient' => true,
                'items' => [
                    [
                        'service_name' => 'Consultation Fee',
                        'unit_price' => 2000,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_create_invoice_for_returning_patient_requires_only_consultation()
    {
        $response = $this->actingAs($this->accountant, 'api')
            ->postJson("/api/v1/patient-records/{$this->patientRecord->id}/invoices", [
                'is_new_patient' => false,
                'items' => [
                    [
                        'service_name' => 'Consultation Fee',
                        'unit_price' => 2000,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_new_patient' => false,
                    'total_amount' => '2000.00',
                ],
            ]);
    }

    public function test_list_invoices_for_patient_record()
    {
        // Create an invoice first
        $invoice = Invoice::create([
            'patient_record_id' => $this->patientRecord->id,
            'patient_id' => $this->patient->id,
            'created_by' => $this->accountant->id,
            'invoice_number' => 'INV-20240101-0001',
            'is_new_patient' => true,
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 0,
            'outstanding_balance' => 3000,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($this->accountant, 'api')
            ->getJson("/api/v1/patient-records/{$this->patientRecord->id}/invoices");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'invoice_number',
                        'total_amount',
                        'status',
                        'items',
                        'payments',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_record_cash_payment_against_invoice()
    {
        $invoice = Invoice::create([
            'patient_record_id' => $this->patientRecord->id,
            'patient_id' => $this->patient->id,
            'created_by' => $this->accountant->id,
            'invoice_number' => 'INV-20240101-0001',
            'is_new_patient' => true,
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 0,
            'outstanding_balance' => 3000,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($this->accountant, 'api')
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'payment_type' => 'cash',
                'amount' => 1500,
                'receipt_reference' => 'CASH-001',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'invoice' => [
                        'amount_paid' => '1500.00',
                        'outstanding_balance' => '1500.00',
                        'status' => 'partially_paid',
                    ],
                ],
            ]);
    }

    public function test_record_hmo_payment_requires_hmo_details()
    {
        $invoice = Invoice::create([
            'patient_record_id' => $this->patientRecord->id,
            'patient_id' => $this->patient->id,
            'created_by' => $this->accountant->id,
            'invoice_number' => 'INV-20240101-0001',
            'is_new_patient' => true,
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 0,
            'outstanding_balance' => 3000,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($this->accountant, 'api')
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'payment_type' => 'hmo_insurance',
                'amount' => 3000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_settle_invoice_updates_patient_record_status_and_routes_to_first_department()
    {
        $invoice = Invoice::create([
            'patient_record_id' => $this->patientRecord->id,
            'patient_id' => $this->patient->id,
            'created_by' => $this->accountant->id,
            'invoice_number' => 'INV-20240101-0001',
            'is_new_patient' => true,
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 3000,
            'outstanding_balance' => 0,
            'status' => 'issued',
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'accountant_id' => $this->accountant->id,
            'payment_type' => 'cash',
            'amount' => 3000,
        ]);

        $response = $this->actingAs($this->accountant, 'api')
            ->postJson("/api/v1/invoices/{$invoice->id}/settle");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'invoice' => [
                        'status' => 'settled',
                    ],
                    'patient_record' => [
                        'status' => 'active',
                        'current_department_id' => $this->clinicalDept->id,
                    ],
                ],
            ]);

        // Verify patient record was updated
        $this->patientRecord->refresh();
        $this->assertEquals('active', $this->patientRecord->status);
        $this->assertEquals($this->clinicalDept->id, $this->patientRecord->current_department_id);
    }

    public function test_settle_invoice_fails_with_outstanding_balance()
    {
        $invoice = Invoice::create([
            'patient_record_id' => $this->patientRecord->id,
            'patient_id' => $this->patient->id,
            'created_by' => $this->accountant->id,
            'invoice_number' => 'INV-20240101-0001',
            'is_new_patient' => true,
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 1500,
            'outstanding_balance' => 1500,
            'status' => 'partially_paid',
        ]);

        $response = $this->actingAs($this->accountant, 'api')
            ->postJson("/api/v1/invoices/{$invoice->id}/settle");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_PAYMENT',
                ],
            ]);
    }

    public function test_generate_receipt_for_settled_invoice()
    {
        $invoice = Invoice::create([
            'patient_record_id' => $this->patientRecord->id,
            'patient_id' => $this->patient->id,
            'created_by' => $this->accountant->id,
            'invoice_number' => 'INV-20240101-0001',
            'is_new_patient' => true,
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 3000,
            'outstanding_balance' => 0,
            'status' => 'settled',
            'settled_at' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'service_name' => 'Registration Fee',
            'unit_price' => 1000,
            'quantity' => 1,
            'subtotal' => 1000,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'accountant_id' => $this->accountant->id,
            'payment_type' => 'cash',
            'amount' => 3000,
        ]);

        $response = $this->actingAs($this->accountant, 'api')
            ->get("/api/v1/invoices/{$invoice->id}/receipt");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_generate_receipt_fails_for_unsettled_invoice()
    {
        $invoice = Invoice::create([
            'patient_record_id' => $this->patientRecord->id,
            'patient_id' => $this->patient->id,
            'created_by' => $this->accountant->id,
            'invoice_number' => 'INV-20240101-0001',
            'is_new_patient' => true,
            'subtotal' => 3000,
            'total_amount' => 3000,
            'amount_paid' => 0,
            'outstanding_balance' => 3000,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($this->accountant, 'api')
            ->getJson("/api/v1/invoices/{$invoice->id}/receipt");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVOICE_NOT_SETTLED',
                ],
            ]);
    }
}
