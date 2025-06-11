<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log; // Adicionar para logging
use Illuminate\Http\Request; // Adicionar para obter o request
use Illuminate\Support\Str; // Adicionar para Str::startsWith

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
    public function boot(Request $request): void // Injete o Request
    {
        // Log para depuração em todos os ambientes (mude o 'if (true)' para condicional após depurar)
        // if (true) { // Exemplo: $this->app->environment('local') ou $this->app->runningInConsole() etc.
        //     Log::info('--------------------------------------------------');
        //     Log::info('[AppServiceProvider] Booting - Request Details:');
        //     Log::info('[AppServiceProvider] Request Scheme (Laravel): ' . $request->getScheme());
        //     Log::info('[AppServiceProvider] Request isSecure (Laravel): ' . ($request->isSecure() ? 'true' : 'false'));
        //     Log::info('[AppServiceProvider] X-Forwarded-Proto Header: ' . $request->header('X-Forwarded-Proto'));
        //     Log::info('[AppServiceProvider] X-Forwarded-For Header: ' . $request->header('X-Forwarded-For'));
        //     Log::info('[AppServiceProvider] Config app.url: ' . config('app.url'));
        //     Log::info('[AppServiceProvider] Env APP_URL: ' . env('APP_URL'));
        //     Log::info('--------------------------------------------------');
        // }

        // Força HTTPS se o APP_URL estiver configurado para HTTPS.
        // Isto é útil para ambientes de produção e desenvolvimento local atrás de um proxy SSL.
        if (Str::startsWith(config('app.url'), 'https')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
            // Adiciona um log para confirmar que o esquema está sendo forçado.
            if (true) { // Mantenha este log durante a depuração
                 Log::info('[AppServiceProvider] APP_URL starts with https. Forcing HTTPS scheme and root URL.');
            }
        } else {
            // Log para quando não estamos a forçar HTTPS (ex: APP_URL é http://)
            if (true) { // Mantenha este log durante a depuração
                Log::info('[AppServiceProvider] APP_URL does not start with https. Not forcing HTTPS scheme explicitly here.');
            }
        }
    }
}
