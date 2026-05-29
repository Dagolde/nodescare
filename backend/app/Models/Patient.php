<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class Patient extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'patient_number', 'full_name', 'date_of_birth', 'gender',
        'phone', 'address', 'next_of_kin_name', 'next_of_kin_phone',
        'chief_complaint', 'hmo_provider_name', 'hmo_policy_number',
        'photo_object_key', 'is_anonymised', 'created_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_anonymised'  => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function records()
    {
        return $this->hasMany(PatientRecord::class);
    }

    public function documents()
    {
        return $this->hasMany(PatientDocument::class);
    }
}
