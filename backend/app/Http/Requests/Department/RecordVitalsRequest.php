<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class RecordVitalsRequest extends FormRequest
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
            'systolic_bp' => ['nullable', 'numeric', 'min:50', 'max:250'],
            'diastolic_bp' => ['nullable', 'numeric', 'min:30', 'max:150'],
            'temperature' => ['nullable', 'numeric', 'min:35', 'max:42'],
            'pulse_rate' => ['nullable', 'integer', 'min:30', 'max:200'],
            'respiratory_rate' => ['nullable', 'integer', 'min:8', 'max:40'],
            'oxygen_saturation' => ['nullable', 'numeric', 'min:70', 'max:100'],
            'blood_glucose' => ['nullable', 'numeric', 'min:20', 'max:600'],
            'weight' => ['nullable', 'numeric', 'min:0.5', 'max:500'],
            'height' => ['nullable', 'numeric', 'min:20', 'max:250'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'systolic_bp.numeric' => 'Systolic blood pressure must be a number.',
            'systolic_bp.min' => 'Systolic blood pressure must be at least 50 mmHg.',
            'systolic_bp.max' => 'Systolic blood pressure cannot exceed 250 mmHg.',
            
            'diastolic_bp.numeric' => 'Diastolic blood pressure must be a number.',
            'diastolic_bp.min' => 'Diastolic blood pressure must be at least 30 mmHg.',
            'diastolic_bp.max' => 'Diastolic blood pressure cannot exceed 150 mmHg.',
            
            'temperature.numeric' => 'Temperature must be a number.',
            'temperature.min' => 'Temperature must be at least 35°C.',
            'temperature.max' => 'Temperature cannot exceed 42°C.',
            
            'pulse_rate.integer' => 'Pulse rate must be a whole number.',
            'pulse_rate.min' => 'Pulse rate must be at least 30 bpm.',
            'pulse_rate.max' => 'Pulse rate cannot exceed 200 bpm.',
            
            'respiratory_rate.integer' => 'Respiratory rate must be a whole number.',
            'respiratory_rate.min' => 'Respiratory rate must be at least 8 breaths/min.',
            'respiratory_rate.max' => 'Respiratory rate cannot exceed 40 breaths/min.',
            
            'oxygen_saturation.numeric' => 'Oxygen saturation must be a number.',
            'oxygen_saturation.min' => 'Oxygen saturation must be at least 70%.',
            'oxygen_saturation.max' => 'Oxygen saturation cannot exceed 100%.',
            
            'blood_glucose.numeric' => 'Blood glucose must be a number.',
            'blood_glucose.min' => 'Blood glucose must be at least 20 mg/dL.',
            'blood_glucose.max' => 'Blood glucose cannot exceed 600 mg/dL.',
            
            'weight.numeric' => 'Weight must be a number.',
            'weight.min' => 'Weight must be at least 0.5 kg.',
            'weight.max' => 'Weight cannot exceed 500 kg.',
            
            'height.numeric' => 'Height must be a number.',
            'height.min' => 'Height must be at least 20 cm.',
            'height.max' => 'Height cannot exceed 250 cm.',
            
            'notes.string' => 'Notes must be text.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'systolic_bp' => 'systolic blood pressure',
            'diastolic_bp' => 'diastolic blood pressure',
            'temperature' => 'temperature',
            'pulse_rate' => 'pulse rate',
            'respiratory_rate' => 'respiratory rate',
            'oxygen_saturation' => 'oxygen saturation',
            'blood_glucose' => 'blood glucose',
            'weight' => 'weight',
            'height' => 'height',
            'notes' => 'notes',
        ];
    }
}
