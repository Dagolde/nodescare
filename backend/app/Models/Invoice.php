<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;
use App\Models\Patient;
use App\Models\User;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'patient_record_id', 'invoice_number',
        'total_amount', 'paid_amount', 'status', 'created_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount'  => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function patientRecord()
    {
        return $this->belongsTo(PatientRecord::class);
    }

    /**
     * Patient via the patient record relationship.
     * Invoice → PatientRecord → Patient
     */
    public function patient()
    {
        return $this->hasOneThrough(
            Patient::class,
            PatientRecord::class,
            'id',             // PatientRecord primary key
            'id',             // Patient primary key
            'patient_record_id', // Invoice foreign key
            'patient_id'      // PatientRecord foreign key to Patient
        );
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
