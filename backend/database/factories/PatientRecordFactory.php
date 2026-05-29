<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PatientRecordFactory extends Factory
{
    protected $model = PatientRecord::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'tenant_id' => app('current_tenant_id'),
            'patient_id' => Patient::factory(),
            'workflow_template_id' => WorkflowTemplate::factory(),
            'status' => 'active',
            'current_department_id' => Department::factory(),
            'current_step_order' => 1,
            'registered_by' => User::factory(),
            'admitted_at' => now(),
        ];
    }
}
