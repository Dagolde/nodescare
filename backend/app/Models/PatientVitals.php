<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class PatientVitals extends Model
{
    use HasUuids;

    protected $table = 'patient_vitals';

    protected $fillable = [
        'tenant_id', 'patient_record_id', 'recorded_by',
        'blood_pressure', 'temperature', 'pulse_rate',
        'respiratory_rate', 'oxygen_saturation', 'weight',
        'height', 'bmi', 'blood_glucose', 'notes',
    ];

    protected $casts = [
        'temperature'       => 'decimal:2',
        'weight'            => 'decimal:2',
        'height'            => 'decimal:2',
        'bmi'               => 'decimal:2',
        'pulse_rate'        => 'integer',
        'respiratory_rate'  => 'integer',
        'oxygen_saturation' => 'integer',
        'blood_glucose'     => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function patientRecord()
    {
        return $this->belongsTo(PatientRecord::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
