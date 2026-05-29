<?php

namespace App\Http\Requests\Auth;

use App\Rules\PasswordComplexity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'string', new PasswordComplexity()],
            'role' => [
                'required',
                Rule::in([
                    'hospital_admin',
                    'doctor',
                    'front_desk_officer',
                    'accountant',
                    'pharmacist',
                    'lab_technician',
                    'department_staff',
                ]),
            ],
            'department_id' => 'nullable|uuid|exists:departments,id',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'The password field is required.',
            'role.in' => 'The selected role is invalid.',
            'department_id.exists' => 'The selected department does not exist.',
        ];
    }
}
