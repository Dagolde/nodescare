<?php

namespace App\Http\Requests\HospitalAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'steps' => 'nullable|array',
            'steps.*.department_id'   => 'required|uuid',
            'steps.*.step_order' => 'required|integer|min:1',
            'steps.*.is_terminal' => 'nullable|boolean',
        ];
    }
}
