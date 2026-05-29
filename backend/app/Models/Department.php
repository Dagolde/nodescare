<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class Department extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'type', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function staff()
    {
        return $this->hasMany(User::class);
    }

    public function currentPatientRecords()
    {
        return $this->hasMany(PatientRecord::class, 'current_department_id');
    }

    public function workingHours()
    {
        return $this->hasMany(DepartmentWorkingHours::class);
    }
}
