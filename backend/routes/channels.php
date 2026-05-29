<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Department channel — authorize if the authenticated user belongs to the department.
 */
Broadcast::channel('department.{departmentId}', function ($user, string $departmentId) {
    return (string) $user->department_id === (string) $departmentId;
});

/**
 * User channel — authorize if the authenticated user's ID matches.
 */
Broadcast::channel('user.{userId}', function ($user, string $userId) {
    return (string) $user->id === (string) $userId;
});

/**
 * Hospital admin channel — authorize if the authenticated user is a hospital admin.
 */
Broadcast::channel('hospital.admin', function ($user) {
    return $user->role === 'hospital_admin';
});

/**
 * Tenant channel — authorize if the authenticated user belongs to the tenant.
 */
Broadcast::channel('tenant.{tenantId}', function ($user, string $tenantId) {
    return (string) $user->tenant_id === (string) $tenantId;
});
