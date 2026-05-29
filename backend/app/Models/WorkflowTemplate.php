<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class WorkflowTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'is_default', 'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function steps()
    {
        return $this->hasMany(WorkflowTemplateStep::class, 'template_id')->orderBy('step_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
