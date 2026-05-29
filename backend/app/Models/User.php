<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use App\Models\Scopes\TenantScope;

class User extends Authenticatable implements JWTSubject
{
    use HasUuids, Notifiable, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'department_id',
        'is_active',
        'is_hod',
        'specialties',
        'created_by',
        'token_version',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'is_hod'                => 'boolean',
        'specialties'           => 'array',
        'token_version'         => 'integer',
        'failed_login_attempts' => 'integer',
        'locked_until'          => 'datetime',
        'email_verified_at'     => 'datetime',
        'password'              => 'hashed',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role'          => $this->role,
            'token_version' => $this->token_version,
            'tenant_id'     => $this->tenant_id,
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isHod(): bool
    {
        return (bool) $this->is_hod;
    }

    public function notificationPreferences()
    {
        return $this->hasOne(UserNotificationPreferences::class);
    }
}
