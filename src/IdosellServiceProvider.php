<?php

namespace Idosell\LaravelAppSdk;

use Idosell\LaravelAppSdk\Console\DoctorCommand;
use Idosell\LaravelAppSdk\Console\InstallCommand;
use Idosell\LaravelAppSdk\Console\LicensesCommand;
use Idosell\LaravelAppSdk\Console\SimulateWebhookCommand;
use Idosell\LaravelAppSdk\Http\Middleware\EnsureIdosellLicense;
use Idosell\LaravelAppSdk\Http\Middleware\LogIdosellWebhook;
use Idosell\LaravelAppSdk\Http\Middleware\VerifyIdosellSign;
use Idosell\LaravelAppSdk\Services\ApiKeyDecryptor;
use Idosell\LaravelAppSdk\Services\AppsApiClient;
use Idosell\LaravelAppSdk\Services\SignatureService;
use Idosell\LaravelAppSdk\Support\LaunchUrlResolver;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Rejestruje SDK: config, serwisy, model licencji, trasy webhooków, aliasy middleware
 * i komendy. Auto-discovery przez composer (`extra.laravel.providers`).
 */
class IdosellServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/idosell.php', 'idosell');

        $this->app->singleton(SignatureService::class, fn (): SignatureService => new SignatureService());
        $this->app->singleton(ApiKeyDecryptor::class, fn (): ApiKeyDecryptor => new ApiKeyDecryptor());
        $this->app->singleton(LaunchUrlResolver::class, fn (): LaunchUrlResolver => new LaunchUrlResolver());

        $this->app->singleton(AppsApiClient::class, fn ($app): AppsApiClient => new AppsApiClient(
            $app->make(SignatureService::class),
        ));

        $this->app->singleton(IdosellManager::class, fn ($app): IdosellManager => new IdosellManager(
            $app->make(SignatureService::class),
            $app->make(ApiKeyDecryptor::class),
            $app->make(AppsApiClient::class),
        ));
    }

    public function boot(): void
    {
        $this->registerMiddlewareAliases();
        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerAboutSection();
    }

    /**
     * Aliasy middleware: `idosell.verify-sign` i `idosell.log-webhook` do własnych tras
     * webhooków (gdy wyłączysz `idosell.routes.enabled`), `idosell.panel` do tras panelu.
     */
    private function registerMiddlewareAliases(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        $router->aliasMiddleware('idosell.verify-sign', VerifyIdosellSign::class);
        $router->aliasMiddleware('idosell.log-webhook', LogIdosellWebhook::class);
        $router->aliasMiddleware('idosell.panel', EnsureIdosellLicense::class);
    }

    private function registerRoutes(): void
    {
        $routesCached = method_exists($this->app, 'routesAreCached') && $this->app->routesAreCached();

        if (!config('idosell.routes.enabled', true) || $routesCached) {
            return;
        }

        Route::group([
            'prefix' => config('idosell.routes.prefix', 'api/idosell/webhooks'),
            'as' => config('idosell.routes.name', 'idosell.webhooks.'),
            'middleware' => array_merge(
                (array) config('idosell.routes.middleware', ['api']),
                // Logowanie PRZED weryfikacją — inaczej nie zobaczysz żądań odrzuconych.
                ['idosell.log-webhook', 'idosell.verify-sign'],
            ),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        });
    }

    private function registerMigrations(): void
    {
        if (config('idosell.licenses.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    private function registerPublishing(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/idosell.php' => config_path('idosell.php'),
        ], ['idosell', 'idosell-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['idosell-migrations']);

        // Baza wiedzy o IdoSell dla asystentów AI (Claude Code i zgodne).
        $this->publishes([
            __DIR__.'/../skills' => base_path('.claude/skills'),
        ], ['idosell-skills']);
    }

    private function registerCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            DoctorCommand::class,
            LicensesCommand::class,
            SimulateWebhookCommand::class,
        ]);
    }

    /**
     * Dokłada sekcję do `php artisan about` — szybki podgląd konfiguracji bez sekretów.
     */
    private function registerAboutSection(): void
    {
        if (!class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('IdoSell App SDK', fn (): array => [
            'Typ aplikacji' => (string) config('idosell.apps.type', 'online'),
            'Application ID' => (string) (config('idosell.apps.application_id') ?: '—'),
            'Deweloper' => config('idosell.apps.developer') ? 'ustawiony' : 'BRAK',
            'Application key' => config('idosell.apps.application_key') ? 'ustawiony' : 'BRAK',
            'Admin API' => (string) config('idosell.admin_api.version', 'v8'),
            'Trasy webhooków' => config('idosell.routes.enabled', true)
                ? '/'.trim((string) config('idosell.routes.prefix'), '/')
                : 'wyłączone',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            SignatureService::class,
            ApiKeyDecryptor::class,
            AppsApiClient::class,
            IdosellManager::class,
            LaunchUrlResolver::class,
        ];
    }
}
