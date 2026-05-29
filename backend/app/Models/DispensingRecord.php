<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class DispensingRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'patient_record_id', 'medications', 'notes', 'pharmacist_id',
    ];

    protected $casts = [
        'medications' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function patientRecord()
    {
        return $this->belongsTo(PatientRecord::class);
    }
}
