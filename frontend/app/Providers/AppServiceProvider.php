<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register services as singletons
        $this->app->singleton(\App\Services\ApiService::class);
        $this->app->singleton(\App\Services\AuthService::class);
        $this->app->singleton(\App\Services\MovieService::class);
        $this->app->singleton(\App\Services\BookingService::class);
        $this->app->singleton(\App\Services\PaymentService::class);
        $this->app->singleton(\App\Services\NotificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
