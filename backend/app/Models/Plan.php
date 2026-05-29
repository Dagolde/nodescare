<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'name',
        'feature_flags',
        'api_rate_limit',
    ];

    protected $casts = [
        'feature_flags' => 'array',
        'api_rate_limit' => 'integer',
    ];

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
