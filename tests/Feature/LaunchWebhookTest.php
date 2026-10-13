<?php

use Idosell\LaravelAppSdk\Events\AppLaunched;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

/**
 * Klucz licencji instalacji z `beforeEach` — każda instalacja ma własny (unikalny indeks).
 */
function launchApiLicense(int $clientId): string
{
    return $clientId === 555001 ? 'LIC-TEST-0000000000000000' : 'LIC-TEST-'.str_pad((string) $clientId, 16, '0', STR_PAD_LEFT);
}

beforeEach(function () {
    // Launch wydaje link tylko dla aktywnej licencji — instalacje używane w testach poniżej.
    foreach ([555001, 42, 123, 4242] as $clientId) {
        license(['client_id' => $clientId, 'api_license' => launchApiLicense($clientId)]);
    }
});

it('zwraca podpisany URL przekierowania do panelu aplikacji', function () {
    $response = $this->postIdosellLaunch(['client_id' => 42, 'api_license' => launchApiLicense(42)]);

    $response->assertOk()->assertJson(['status' => 'ok']);
    expect($response->json('sign'))->toBe($this->idosellSign());

    $redirect = $response->json('redirect');

    expect($redirect)->toContain('/panel')
        ->and($redirect)->toContain('client=42')
        ->and($redirect)->toContain('signature=');

    // Podpis URL to jedyna identyfikacja sprzedawcy przy wejściu z panelu sklepu.
    expect(URL::hasValidSignature(Request::create($redirect)))->toBeTrue();
});

it('generuje adres, którym da się wejść do panelu aplikacji', function () {
    $redirect = $this->postIdosellLaunch()->json('redirect');

    $this->get($redirect)->assertOk()->assertSee('panel aplikacji');
});

it('odrzuca wejście do panelu bez ważnego podpisu URL', function () {
    $this->get(route('app.panel', ['client' => 42]))->assertForbidden();
});

it('wygasza adres po czasie z konfiguracji', function () {
    config(['idosell.launch.ttl' => 30]);

    $redirect = $this->postIdosellLaunch()->json('redirect');

    $this->travel(31)->minutes();

    $this->get($redirect)->assertForbidden();
});

it('pozwala podmienić sposób budowania adresu', function () {
    Idosell::resolveLaunchUrlUsing(fn (array $payload): string => 'https://moja-aplikacja.example.com/wejscie/'.$payload['client_id']);

    $this->postIdosellLaunch(['client_id' => 4242, 'api_license' => launchApiLicense(4242)])
        ->assertJson(['redirect' => 'https://moja-aplikacja.example.com/wejscie/4242']);
});

it('używa stałego adresu, gdy nie wskazano trasy', function () {
    config([
        'idosell.launch.route' => null,
        'idosell.launch.url' => 'https://moja-aplikacja.example.com/start',
    ]);

    $this->postIdosellLaunch()->assertJson(['redirect' => 'https://moja-aplikacja.example.com/start']);
});

it('zwraca błąd, gdy nie skonfigurowano dokąd przekierować', function () {
    config(['idosell.launch.route' => null, 'idosell.launch.url' => null]);

    $this->postIdosellLaunch()
        ->assertOk()
        ->assertJson(['status' => 'error'])
        ->assertJsonMissingPath('redirect');
});

it('emituje zdarzenie uruchomienia aplikacji', function () {
    Event::fake([AppLaunched::class]);

    $this->postIdosellLaunch(['client_id' => 123, 'api_license' => launchApiLicense(123)]);

    Event::assertDispatched(AppLaunched::class, fn (AppLaunched $e): bool => $e->payload['client_id'] === 123
        && str_contains($e->redirect, '/panel'));
});

it('odrzuca uruchomienie z nieprawidłowym podpisem', function () {
    $this->postIdosellLaunch(['sign' => 'zly-podpis'])->assertJson(['status' => 'error']);
});

it('nie wydaje linku do panelu bez aktywnej licencji instalacji', function (array $payload) {
    Event::fake([AppLaunched::class]);
    license(['client_id' => 777001, 'active' => false, 'api_license' => launchApiLicense(777001)]);

    $this->postIdosellLaunch($payload)
        ->assertOk()
        ->assertJson(['status' => 'error'])
        ->assertJsonMissingPath('redirect');

    Event::assertNotDispatched(AppLaunched::class);
})->with([
    'nieznany sprzedawca' => [['client_id' => 999001]],
    'licencja nieaktywna' => [['client_id' => 777001, 'api_license' => 'LIC-TEST-0000000000777001']],
    'inna aplikacja' => [['client_id' => 555001, 'application_id' => 1]],
    'cudzy api_license' => [['client_id' => 555001, 'api_license' => 'LIC-OTHER-0000000000000000']],
]);

it('odrzuca uruchomienie bez api_license', function () {
    $this->postIdosellLaunch(['api_license' => null])->assertOk()->assertJson(['status' => 'error']);
});

it('sprawdza licencję także przy własnym resolverze adresu', function () {
    Idosell::resolveLaunchUrlUsing(fn (array $payload): string => 'https://moja-aplikacja.example.com/wejscie');

    $this->postIdosellLaunch(['client_id' => 999001])
        ->assertJson(['status' => 'error'])
        ->assertJsonMissingPath('redirect');
});
