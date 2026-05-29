<?php

namespace App\Http\Requests\HospitalAdmin;

use App\Rules\PasswordComplexity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'password' => ['required', 'string', 'min:10', new PasswordComplexity()],
            'role' => [
                'required',
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
