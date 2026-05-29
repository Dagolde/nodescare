<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkingHoursRequest extends FormRequest
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
        return [
            'working_hours' => ['required', 'array', 'size:7'],
            'working_hours.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'working_hours.*.is_open' => ['required', 'boolean'],
            'working_hours.*.opening_time' => ['nullable', 'date_format:H:i', 'required_if:working_hours.*.is_open,true'],
            'working_hours.*.closing_time' => ['nullable', 'date_format:H:i', 'required_if:working_hours.*.is_open,true', 'after:working_hours.*.opening_time'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'working_hours.required' => 'Working hours data is required.',
            'working_hours.array' => 'Working hours must be an array.',
            'working_hours.size' => 'Working hours must include all 7 days of the week.',
            
            'working_hours.*.day_of_week.required' => 'Day of week is required for each entry.',
            'working_hours.*.day_of_week.integer' => 'Day of week must be a number.',
            'working_hours.*.day_of_week.min' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'working_hours.*.day_of_week.max' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            
            'working_hours.*.is_open.required' => 'Open status is required for each day.',
            'working_hours.*.is_open.boolean' => 'Open status must be true or false.',
            
            'working_hours.*.opening_time.date_format' => 'Opening time must be in HH:MM format (e.g., 08:00).',
            'working_hours.*.opening_time.required_if' => 'Opening time is required when the department is open.',
            
            'working_hours.*.closing_time.date_format' => 'Closing time must be in HH:MM format (e.g., 17:00).',
            'working_hours.*.closing_time.required_if' => 'Closing time is required when the department is open.',
            'working_hours.*.closing_time.after' => 'Closing time must be after opening time.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'working_hours' => 'working hours',
            'working_hours.*.day_of_week' => 'day of week',
            'working_hours.*.is_open' => 'open status',
            'working_hours.*.opening_time' => 'opening time',
            'working_hours.*.closing_time' => 'closing time',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure working_hours is an array
        if ($this->has('working_hours') && is_string($this->working_hours)) {
            $this->merge([
                'working_hours' => json_decode($this->working_hours, true),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workingHours = $this->input('working_hours', []);
            
            // Check for duplicate days
            $days = array_column($workingHours, 'day_of_week');
            if (count($days) !== count(array_unique($days))) {
                $validator->errors()->add('working_hours', 'Each day of the week must appear exactly once.');
            }
            
            // Validate that all days 0-6 are present
            $expectedDays = [0, 1, 2, 3, 4, 5, 6];
            $missingDays = array_diff($expectedDays, $days);
            if (!empty($missingDays)) {
                $validator->errors()->add('working_hours', 'All 7 days of the week must be included.');
            }
        });
    }
}
