<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('doctor potwierdza poprawną konfigurację', function () {
    $this->artisan('idosell:doctor')->assertSuccessful();
});

it('doctor wykrywa brak danych aplikacji', function () {
    config(['idosell.apps.developer' => '', 'idosell.apps.application_key' => '']);

    $this->artisan('idosell:doctor')
        ->expectsOutputToContain('IDOSELL_DEVELOPER')
        ->assertFailed();
});

it('doctor wykrywa brak adresu przekierowania po uruchomieniu', function () {
    config(['idosell.launch.route' => null, 'idosell.launch.url' => null]);

    $this->artisan('idosell:doctor')->assertFailed();
});

it('doctor sprawdza długość IV z keyset w trybie --live', function () {
    Http::fake([
        '*keyset' => Http::response('za-krotki-iv', 200),
        '*application/license' => Http::response(['status' => 'ok', 'licenses' => []], 200),
    ]);
    Http::preventStrayRequests();

    $this->artisan('idosell:doctor --live')->assertFailed();
});

it('doctor przechodzi w trybie --live przy poprawnych odpowiedziach platformy', function () {
    $this->fakeIdosellApps();

    $this->artisan('idosell:doctor --live')->assertSuccessful();
});

it('install pokazuje adresy webhooków do wpisania w panelu dewelopera', function () {
    $this->artisan('idosell:install')
        ->expectsOutputToContain('https://moja-aplikacja.example.com/api/idosell/webhooks/new-license')
        ->expectsOutputToContain('https://moja-aplikacja.example.com/api/idosell/webhooks/remove-license')
        ->expectsOutputToContain('https://moja-aplikacja.example.com/api/idosell/webhooks/launch')
        ->assertSuccessful();
});

it('licenses wypisuje instalacje bez sekretów', function () {
    license(['client_id' => 424242, 'api_key' => 'BARDZO-TAJNY-KLUCZ', 'api_license' => 'LIC-TAJNA-XYZ']);

    // Przez Artisan::call, nie $this->artisan(): `expectsOutputToContain` zużywa jedno
    // wywołanie zapisu na frazę, więc dwie frazy z tego samego wiersza tabeli nigdy nie
    // dopasują się naraz.
    expect(Artisan::call('idosell:licenses'))->toBe(0);

    $output = Artisan::output();

    expect($output)->toContain('424242')
        ->and($output)->toContain('demo-shop.example.com')
        ->and($output)->not->toContain('BARDZO-TAJNY-KLUCZ')
        ->and($output)->not->toContain('LIC-TAJNA-XYZ');
});

it('licenses informuje, gdy nie ma żadnej instalacji', function () {
    $this->artisan('idosell:licenses --active')->assertSuccessful();
});

it('simulate wysyła webhook z prawidłowym podpisem i szyfrowanym kluczem', function () {
    $this->fakeIdosellApps([
        // Symulator strzela pod własną aplikację — w teście przechwytujemy to wywołanie.
        'moja-aplikacja.example.com/*' => Http::response(['status' => 'ok', 'sign' => $this->idosellSign()], 200),
    ]);

    $this->artisan('idosell:simulate new-license --client=880001')->assertSuccessful();

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), 'idosell/webhooks/new-license')) {
            return false;
        }

        $data = $request->data();

        return $data['client_id'] === 880001
            && $data['sign'] === $this->idosellSign()
            && $data['api_key'] !== 'admin-api-key-0000000000000000';
    });
});

it('simulate jest zablokowany na produkcji', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->artisan('idosell:simulate new-license')->assertFailed();
});
