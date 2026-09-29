<?php

namespace Idosell\LaravelAppSdk\Support;

use Closure;
use Idosell\LaravelAppSdk\Exceptions\ConfigurationException;
use Illuminate\Support\Facades\URL;

/**
 * Wyznacza adres, pod który panel sprzedawcy przekieruje po uruchomieniu aplikacji.
 *
 * Domyślnie budujemy PODPISANY URL czasowy do trasy z configu. Podpis jest jedyną
 * identyfikacją sprzedawcy przy wejściu z panelu, więc trasa docelowa musi mieć
 * middleware `signed` — inaczej każdy mógłby wejść z dowolnym `client_id`.
 */
class LaunchUrlResolver
{
    /** @var (Closure(array<string, mixed>): string)|null */
    protected static ?Closure $resolver = null;

    /**
     * Podmienia sposób budowania adresu (np. własny token zamiast podpisanego URL).
     *
     * @param  (Closure(array<string, mixed>): string)|null  $callback
     */
    public static function resolveUsing(?Closure $callback): void
    {
        static::$resolver = $callback;
    }

    /**
     * @param  array<string, mixed>  $payload  zwalidowany payload webhooka uruchomienia
     */
    public function resolve(array $payload): string
    {
        if (static::$resolver !== null) {
            return (static::$resolver)($payload);
        }

        $route = config('idosell.launch.route');

        if ($route) {
            return $this->routeUrl((string) $route, $payload);
        }

        $url = config('idosell.launch.url');

        if ($url) {
            return (string) $url;
        }

        throw new ConfigurationException(
            'Nie wiadomo, dokąd przekierować sprzedawcę po uruchomieniu aplikacji. '
            .'Ustaw `idosell.launch.route` (nazwa trasy) albo `idosell.launch.url`, '
            .'ewentualnie własny resolver: Idosell::resolveLaunchUrlUsing(...).'
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function routeUrl(string $route, array $payload): string
    {
        $parameters = LaunchParameters::fromPayload($payload);

        if (!config('idosell.launch.signed', true)) {
            return URL::route($route, $parameters);
        }

        return URL::temporarySignedRoute(
            $route,
            now()->addMinutes((int) config('idosell.launch.ttl', 30)),
            $parameters,
        );
    }
}
