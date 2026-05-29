<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'recipient_id', 'type', 'channel', 'payload', 'status', 'attempts',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}
