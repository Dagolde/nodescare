<?php

namespace App\Http\Requests\HospitalAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $departmentId = $this->route('id');

        return [
            'name' => 'nullable|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->ignore($departmentId),
            ],
            'type' => [
                'nullable',
                'string',
                Rule::in([
                    'clinical',
                    'pharmacy',
                    'laboratory',
                    'front_desk',
                    'triage',
                    'ward',
                    'icu_ae',
                    'accounts',
                    'other',
                ]),
            ],
            'is_active' => 'nullable|boolean',
        ];
    }
}
