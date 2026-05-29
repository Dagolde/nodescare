<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'tenant.resolve'      => \App\Http\Middleware\TenantResolutionMiddleware::class,
            'tenant.verify'       => \App\Http\Middleware\TenantVerificationMiddleware::class,
            'jwt.auth'            => \App\Http\Middleware\JWTAuthMiddleware::class,
            'super.admin.auth'    => \App\Http\Middleware\SuperAdminAuthMiddleware::class,
            'hospital.admin.auth' => \App\Http\Middleware\HospitalAdminAuthMiddleware::class,
            'department.auth'     => \App\Http\Middleware\DepartmentAuthMiddleware::class,
            'rbac'                => \App\Http\Middleware\RBACMiddleware::class,
            'tenant.scope'        => \App\Http\Middleware\TenantScopeMiddleware::class,
            'maintenance.mode'    => \App\Http\Middleware\MaintenanceModeMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
