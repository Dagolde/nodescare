<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class DepartmentWorkingHours extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'department_id',
        'day_of_week',
        'is_open',
        'opening_time',
        'closing_time',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'day_of_week' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
