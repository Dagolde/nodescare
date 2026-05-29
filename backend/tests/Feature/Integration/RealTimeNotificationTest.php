<?php

namespace Tests\Feature\Integration;

use App\Events\PatientRouted;
use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Real-time notification delivery integration test.
 *
 * Verifies that the PatientRouted broadcast event is dispatched
 * within the same request when a patient is routed to a department.
 *
 * Requirements: 4.2, 11.1
 */
class RealTimeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Department $triageDept;
    private Department $doctorDept;
    private User $triageUser;
    private WorkflowTemplate $template;

    /** Middleware to bypass for protected routes. */
    private array $bypassMiddleware = ['tenant.resolve', 'jwt.auth', 'rbac', 'tenant.scope'];

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->template = WorkflowTemplate::create([
            'name'        => 'Test Workflow',
            'description' => 'Integration test workflow',
            'is_default'  => true,
            'created_by'  => null,
        ]);

        WorkflowTemplateStep::create([
            'template_id'   => $this->template->id,
            'department_id' => $this->triageDept->id,
            'step_order'    => 1,
            'is_terminal'   => false,
        ]);

        WorkflowTemplateStep::create([
            'template_id'   => $this->template->id,
            'department_id' => $this->doctorDept->id,
            'step_order'    => 2,
            'is_terminal'   => true,
        ]);

        $this->triageUser = User::factory()->create([
            'role'          => 'department_staff',
            'department_id' => $this->triageDept->id,
            'is_active'     => true,
        ]);
    }

    /**
     * Requirement 4.2 — When a patient record is routed to a department,
     * the Notification_Service SHALL deliver a real-time alert within 5 seconds.
     *
     * Requirement 11.1 — PatientRouted broadcast event is dispatched within
     * the routing request.
     */
    public function test_patient_routed_event_is_dispatched_on_routing(): void
    {
        Event::fake([PatientRouted::class]);

        $patient = Patient::create([
            'patient_number'  => 'P-20240101-0010',
            'full_name'       => 'Event Test Patient',
            'date_of_birth'   => '1990-01-01',
            'gender'          => 'male',
            'phone'           => '08011110010',
            'chief_complaint' => 'Fever',
            'created_by'      => $this->triageUser->id,
        ]);

        $patientRecord = PatientRecord::create([
            'patient_id'            => $patient->id,
            'workflow_template_id'  => $this->template->id,
            'status'                => 'active',
            'current_department_id' => $this->triageDept->id,
            'current_step_order'    => 1,
            'registered_by'         => $this->triageUser->id,
        ]);

        $response = $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->triageUser)
            ->postJson("/api/v1/patient-records/{$patientRecord->id}/complete", [
                'notes' => 'Vitals stable. Routing to doctor.',
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(PatientRouted::class, function (PatientRouted $event) use ($patientRecord) {
            return $event->patientRecordId === $patientRecord->id
                && $event->departmentId === $this->doctorDept->id;
        });
    }

    /**
     * Requirement 11.1 — PatientRouted event is dispatched exactly once per routing.
     */
    public function test_patient_routed_event_dispatched_exactly_once(): void
    {
        Event::fake([PatientRouted::class]);

        $patient = Patient::create([
            'patient_number'  => 'P-20240101-0011',
            'full_name'       => 'Single Event Patient',
            'date_of_birth'   => '1992-04-20',
            'gender'          => 'female',
            'phone'           => '08011110011',
            'chief_complaint' => 'Headache',
            'created_by'      => $this->triageUser->id,
        ]);

        $patientRecord = PatientRecord::create([
            'patient_id'            => $patient->id,
            'workflow_template_id'  => $this->template->id,
            'status'                => 'active',
            'current_department_id' => $this->triageDept->id,
            'current_step_order'    => 1,
            'registered_by'         => $this->triageUser->id,
        ]);

        $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->triageUser)
            ->postJson("/api/v1/patient-records/{$patientRecord->id}/complete", [
                'notes' => 'Routing to doctor.',
            ]);

        Event::assertDispatchedTimes(PatientRouted::class, 1);
    }

    /**
     * Requirement 4.2 — The broadcast event targets the correct department channel.
     */
    public function test_patient_routed_event_targets_correct_department_channel(): void
    {
        Event::fake([PatientRouted::class]);

        $patient = Patient::create([
            'patient_number'  => 'P-20240101-0012',
            'full_name'       => 'Channel Test Patient',
            'date_of_birth'   => '1988-09-12',
            'gender'          => 'male',
            'phone'           => '08011110012',
            'chief_complaint' => 'Cough',
            'created_by'      => $this->triageUser->id,
        ]);

        $patientRecord = PatientRecord::create([
            'patient_id'            => $patient->id,
            'workflow_template_id'  => $this->template->id,
            'status'                => 'active',
            'current_department_id' => $this->triageDept->id,
            'current_step_order'    => 1,
            'registered_by'         => $this->triageUser->id,
        ]);

        $this->withoutMiddleware($this->bypassMiddleware)
            ->actingAs($this->triageUser)
            ->postJson("/api/v1/patient-records/{$patientRecord->id}/complete", [
                'notes' => 'Routing.',
            ]);

        Event::assertDispatched(PatientRouted::class, function (PatientRouted $event) {
            $channels = $event->broadcastOn();
            $channelName = is_array($channels)
                ? $channels[0]->name
                : $channels->name;

            return str_contains($channelName, $this->doctorDept->id);
        });
    }
}
