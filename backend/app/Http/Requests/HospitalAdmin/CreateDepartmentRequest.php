<?php

namespace App\Http\Requests\HospitalAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code',
            'type' => [
                'required',
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
