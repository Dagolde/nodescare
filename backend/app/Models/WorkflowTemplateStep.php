<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class WorkflowTemplateStep extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'template_id', 'department_id', 'step_order', 'is_terminal',
    ];

    protected $casts = [
        'is_terminal' => 'boolean',
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
