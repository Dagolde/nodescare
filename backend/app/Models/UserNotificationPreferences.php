<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class UserNotificationPreferences extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'patient_arrivals_enabled',
        'staff_actions_enabled',
        'system_alerts_enabled',
        'email_enabled',
        'sms_enabled',
        'in_app_enabled',
    ];

    protected $casts = [
        'patient_arrivals_enabled' => 'boolean',
        'staff_actions_enabled' => 'boolean',
        'system_alerts_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
