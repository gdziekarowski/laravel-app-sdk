<?php

use Idosell\LaravelAppSdk\IdosellManager;
use Idosell\LaravelAppSdk\Models\IdosellLicense;

if (!function_exists('idosell_route')) {
    /**
     * Podpisany URL czasowy do trasy panelu z kontekstem instalacji bieżącej (lub podanej) licencji.
     *
     * Skrót do `Idosell::panelUrl()` — do linków i akcji formularzy w widokach panelu.
     *
     * @param  array<string, mixed>  $parameters
     */
    function idosell_route(string $name, array $parameters = [], ?IdosellLicense $license = null): string
    {
        return app(IdosellManager::class)->panelUrl($name, $parameters, $license);
    }
}
