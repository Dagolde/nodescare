<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_record_id' => 'required|uuid',
            'document_type' => ['required', Rule::in(['registration', 'lab_result', 'prescription', 'other'])],
            'object_key' => 'required|string|max:500',
            'original_filename' => 'required|string|max:255',
            'file_size_bytes' => 'required|integer|min:1|max:10485760', // 10 MB max
        ];
    }

    public function messages(): array
    {
        return [
            'patient_record_id.required' => 'The patient record ID is required.',
            'patient_record_id.exists' => 'The selected patient record does not exist.',
            'document_type.required' => 'The document type is required.',
            'document_type.in' => 'The selected document type is invalid.',
            'object_key.required' => 'The object key is required.',
            'original_filename.required' => 'The original filename is required.',
            'file_size_bytes.required' => 'The file size is required.',
            'file_size_bytes.max' => 'The file size must not exceed 10 MB.',
        ];
    }
}
