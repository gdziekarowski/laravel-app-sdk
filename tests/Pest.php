<?php

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Konfiguracja Pest
|--------------------------------------------------------------------------
|
| Testy działają na aplikacji budowanej przez Orchestra Testbench (baza sqlite
| w pamięci). Dane w testach są WYŁĄCZNIE syntetyczne — obowiązuje RODO.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Licencja sprzedawcy z sensownymi wartościami domyślnymi.
 *
 * @param  array<string, mixed>  $overrides
 */
function license(array $overrides = []): IdosellLicense
{
    return IdosellLicense::create(array_merge([
        'client_id' => 555001,
        'application_id' => 4242,
        'api_url' => 'https://demo-shop.example.com/api',
        'api_license' => 'LIC-TEST-0000000000000000',
        'api_key' => 'admin-api-key-0000000000000000',
        'authorization_type' => 'key',
        'active' => true,
        'installation_confirmed' => true,
        'meta' => ['selected_shops' => [['id' => 1, 'name' => 'Sklep 1']]],
    ], $overrides));
}
