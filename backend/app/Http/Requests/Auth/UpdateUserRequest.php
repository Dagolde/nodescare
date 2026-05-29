<?php

namespace App\Http\Requests\Auth;

use App\Rules\PasswordComplexity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('id');

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => 'nullable|string|max:20',
            'password' => ['sometimes', 'required', 'string', new PasswordComplexity()],
            'role' => [
                'sometimes',
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
            'is_active' => 'sometimes|boolean',
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
