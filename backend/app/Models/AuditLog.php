<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class AuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'actor_id', 'actor_role', 'action',
        'resource_type', 'resource_id', 'description', 'ip_address',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id')->withoutGlobalScope(TenantScope::class);
    }
}
