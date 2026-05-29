<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class GenerateReportRequest extends FormRequest
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
            'start_date' => ['required', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.before_or_equal' => 'Start date must be before or equal to end date.',
            
            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'end_date.before_or_equal' => 'End date cannot be in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'start_date' => 'start date',
            'end_date' => 'end date',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('start_date') && $this->has('end_date')) {
                try {
                    $startDate = Carbon::parse($this->input('start_date'));
                    $endDate = Carbon::parse($this->input('end_date'));
                    
                    // Check if date range exceeds 365 days
                    $daysDiff = $startDate->diffInDays($endDate);
                    
                    if ($daysDiff > 365) {
                        $validator->errors()->add(
                            'end_date',
                            'The date range cannot exceed 365 days. Current range: ' . $daysDiff . ' days.'
                        );
                    }
                } catch (\Exception $e) {
                    // Date parsing errors are already handled by the date validation rule
                }
            }
        });
    }
}
