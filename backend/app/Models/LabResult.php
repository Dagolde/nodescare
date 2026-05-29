<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class LabResult extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'patient_record_id', 'test_name', 'result',
        'unit', 'reference_range', 'status', 'notes', 'technician_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function patientRecord()
    {
        return $this->belongsTo(PatientRecord::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
