<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled in the controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $departmentId = $this->route('id') ?? auth()->user()->department_id;
        $tenantId = session('tenant_id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where('tenant_id', $tenantId)
                    ->ignore($departmentId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20', 'regex:/^[\d\s\+\-\(\)]+$/'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Department name is required.',
            'name.string' => 'Department name must be text.',
            'name.max' => 'Department name cannot exceed 255 characters.',
            'name.unique' => 'A department with this name already exists in your hospital.',
            
            'description.string' => 'Description must be text.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            
            'contact_email.email' => 'Please provide a valid email address.',
            'contact_email.max' => 'Email address cannot exceed 255 characters.',
            
            'contact_phone.string' => 'Phone number must be text.',
            'contact_phone.max' => 'Phone number cannot exceed 20 characters.',
            'contact_phone.regex' => 'Please provide a valid phone number format.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'department name',
            'description' => 'description',
            'contact_email' => 'contact email',
            'contact_phone' => 'contact phone',
        ];
    }
}
