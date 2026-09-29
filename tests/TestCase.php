<?php

namespace Idosell\LaravelAppSdk\Tests;

use Idosell\LaravelAppSdk\IdosellServiceProvider;
use Idosell\LaravelAppSdk\Support\LaunchUrlResolver;
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\RateLimiter;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithIdosell;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolver jest statyczny — bez czyszczenia przeciekałby między testami.
        LaunchUrlResolver::resolveUsing(null);
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [IdosellServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.url', 'https://moja-aplikacja.example.com');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('idosell.apps.developer', 'dev-login');
        $app['config']->set('idosell.apps.application_key', str_repeat('K', 32));
        $app['config']->set('idosell.apps.application_id', 4242);
        $app['config']->set('idosell.launch.route', 'app.panel');

        // Grupa `api` z testbencha używa `throttle:api` — limiter musimy zdefiniować sami.
        RateLimiter::for('api', fn (): Limit => Limit::perMinute(600));
    }

    protected function defineRoutes($router): void
    {
        // Panel aplikacji, do którego kierujemy sprzedawcę po uruchomieniu w panelu sklepu.
        // Middleware podany klasą, nie aliasem — testbench nie musi mieć aliasu `signed`.
        $router->get('/panel', fn (): string => 'panel aplikacji')
            ->middleware(ValidateSignature::class)
            ->name('app.panel');
    }
}
