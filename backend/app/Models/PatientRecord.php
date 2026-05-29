<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;
use App\Models\User;

class PatientRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'patient_id', 'workflow_template_id', 'status',
        'current_department_id', 'current_step_order', 'registered_by',
        'return_to_department_id', 'pending_payment_context',
        'admitted_at', 'discharged_at',
        'visit_type', 'assigned_doctor_id',
    ];

    // Valid status values (for reference — enforced by DB enum)
    public const STATUSES = [
        'pending_payment', 'active', 'in_transit',
        'admitted', 'discharged', 'completed', 'drugs_given',
    ];

    protected $casts = [
        'admitted_at'   => 'datetime',
        'discharged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function currentDepartment()
    {
        return $this->belongsTo(Department::class, 'current_department_id');
    }

    public function workflowTemplate()
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    public function journeyEvents()
    {
        return $this->hasMany(PatientJourneyEvent::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function registrar()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function assignedDoctor()
    {
        return $this->belongsTo(User::class, 'assigned_doctor_id');
    }
}
