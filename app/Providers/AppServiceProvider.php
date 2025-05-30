<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
            if (app()->environment('production')) {
            // Força o uso de HTTPS em URLs geradas pelo Laravel
            URL::forceScheme('https');
        }
    }
}
