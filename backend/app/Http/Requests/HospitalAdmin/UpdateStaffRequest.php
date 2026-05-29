<?php

namespace App\Http\Requests\HospitalAdmin;

use App\Rules\PasswordComplexity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffId = $this->route('id');

        return [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => ['nullable', 'string', 'min:10', new PasswordComplexity()],
            'role' => [
                'nullable',
                'string',
                Rule::in([
                    'hospital_admin',
                    'doctor',
                    'front_desk_officer',
                    'accountant',
                    'pharmacist',
                    'lab_technician',
                    'nurse',
                    'triage',
                    'department_staff',
                ]),
            ],
            'department_id' => 'nullable|uuid',
            'is_active' => 'nullable|boolean',
            'is_hod' => 'nullable|boolean',
            'specialties'   => 'nullable|array',
            'specialties.*' => 'string',
        ];
    }
}
