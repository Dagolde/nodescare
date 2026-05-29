<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Legacy / existing
            'mobile_app_version'        => ['nullable', 'string', 'max:50', 'regex:/^\d+\.\d+\.\d+$/'],
            'api_rate_limit'            => ['nullable', 'integer', 'min:1', 'max:10000'],
            'maintenance_mode'          => ['nullable', 'boolean'],

            // Mobile App
            'mobile_app_min_version'    => ['nullable', 'string', 'max:50', 'regex:/^\d+\.\d+\.\d+$/'],
            'mobile_app_force_update'   => ['nullable', 'boolean'],

            // Maintenance
            'maintenance_message'       => ['nullable', 'string', 'max:1000'],

            // File uploads
            'max_file_upload_size_mb'   => ['nullable', 'integer', 'min:1', 'max:500'],

            // Security
            'session_timeout_minutes'   => ['nullable', 'integer', 'min:1', 'max:1440'],
            'max_failed_login_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'lockout_duration_minutes'  => ['nullable', 'integer', 'min:1', 'max:1440'],

            // Workflow
            'default_workflow_template' => ['nullable', 'string', 'uuid'],

            // Contact / branding
            'support_email'             => ['nullable', 'email', 'max:255'],
            'support_phone'             => ['nullable', 'string', 'max:50'],
            'system_name'               => ['nullable', 'string', 'max:255'],
        ];
    }
}
