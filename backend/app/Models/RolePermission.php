<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $fillable = ['role', 'permission'];

    // Only created_at — no updated_at
    const UPDATED_AT = null;
}
