<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'next_of_kin_name' => 'nullable|string|max:255',
            'next_of_kin_phone' => 'nullable|string|max:20',
            'chief_complaint' => 'required|string',
            'hmo_provider_name' => 'nullable|string|max:255',
            'hmo_policy_number' => 'nullable|string|max:255',
            'photo_object_key' => 'nullable|string|max:500',
            'photo' => 'nullable|image|mimes:jpeg,jpg,png|max:5120', // 5 MB max, takes priority over photo_object_key
            'visit_type'         => 'nullable|string|max:100',
            'assigned_doctor_id' => 'nullable|uuid',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'The full name field is required.',
            'date_of_birth.required' => 'The date of birth field is required.',
            'date_of_birth.before' => 'The date of birth must be before today.',
            'gender.required' => 'The gender field is required.',
            'gender.in' => 'The selected gender is invalid.',
            'phone.required' => 'The phone number field is required.',
            'chief_complaint.required' => 'The chief complaint field is required.',
        ];
    }
}
