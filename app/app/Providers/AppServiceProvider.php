<?php

namespace App\Providers;

use App\Services\OpenFinance\TecnoSpeedClient;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TecnoSpeedClient::class, fn () => TecnoSpeedClient::fromConfig());

        if ($this->app->environment('local')) {
            if (class_exists(TelescopeServiceProvider::class)) {
                $this->app->register(TelescopeServiceProvider::class);
            }
            if (class_exists(\App\Providers\TelescopeServiceProvider::class)) {
                $this->app->register(\App\Providers\TelescopeServiceProvider::class);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
