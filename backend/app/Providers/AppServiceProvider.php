<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind a default null tenant_id so TenantScope doesn't fail before resolution
        $this->app->bind('current_tenant_id', fn () => null);
        $this->app->bind('current_tenant', fn () => null);
    }

    public function boot(): void
    {
        //
    }
}
