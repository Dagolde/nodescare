<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;
use App\Models\User;

class PatientJourneyEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'patient_record_id', 'event_type',
        'from_department_id', 'to_department_id', 'notes', 'metadata', 'performed_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function patientRecord()
    {
        return $this->belongsTo(PatientRecord::class);
    }

    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
