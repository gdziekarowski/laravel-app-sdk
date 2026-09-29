<?php

namespace Idosell\LaravelAppSdk\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sprzedawca uruchomił aplikację w panelu — zwróciliśmy adres przekierowania.
 *
 * Przydatne do telemetrii („kiedy ostatnio korzystano z aplikacji") i do leniwego
 * dokończenia konfiguracji instalacji.
 */
class AppLaunched
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  zwalidowany payload webhooka uruchomienia
     */
    public function __construct(
        public readonly array $payload,
        public readonly string $redirect,
    ) {}
}
