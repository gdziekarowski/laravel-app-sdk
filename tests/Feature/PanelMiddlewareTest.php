<?php

use Idosell\LaravelAppSdk\Exceptions\IdosellException;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Http\Middleware\EnsureIdosellLicense;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $describe = fn (Request $request): array => [
        'client' => Idosell::currentLicense()?->client_id,
        'attribute' => $request->attributes->get(EnsureIdosellLicense::ATTRIBUTE)?->client_id,
        'container' => app(IdosellLicense::class)->client_id,
    ];

    Route::middleware('idosell.panel')->group(function () use ($describe): void {
        Route::get('/app/dashboard', $describe)->name('test.dashboard');
        Route::post('/app/shops/fetch', $describe)->name('test.shops.fetch');
    });

    Route::get('/app/by-class', $describe)
        ->middleware(EnsureIdosellLicense::class)
        ->name('test.by-class');

    $this->license = IdosellLicense::factory()->create(['client_id' => 555001]);
});

/**
 * Podpisany URL czasowy z dowolnymi parametrami (bez przechodzenia przez licencję).
 *
 * @param  array<string, mixed>  $parameters
 */
function signedPanelUrl(string $route, array $parameters): string
{
    return URL::temporarySignedRoute($route, now()->addMinutes(30), $parameters);
}

it('wpuszcza z podpisanym URL i udostępnia licencję', function () {
    $this->get(signedPanelUrl('test.dashboard', ['client' => 555001, 'application' => 4242]))
        ->assertOk()
        ->assertExactJson(['client' => 555001, 'attribute' => 555001, 'container' => 555001]);
});

it('bierze aplikację z konfiguracji, gdy nie ma jej w URL', function () {
    $this->get(signedPanelUrl('test.dashboard', ['client' => 555001]))
        ->assertOk()
        ->assertJson(['client' => 555001]);
});

it('działa z middleware podanym klasą', function () {
    $this->get(Idosell::panelUrl('test.by-class', [], $this->license))
        ->assertOk()
        ->assertJson(['client' => 555001]);
});

it('odrzuca wejście bez podpisu', function () {
    $this->get(route('test.dashboard', ['client' => 555001, 'application' => 4242]))
        ->assertForbidden();
});

it('odrzuca podrobiony podpis', function () {
    $url = signedPanelUrl('test.dashboard', ['client' => 555001, 'application' => 4242]);

    $this->get(str_replace('client=555001', 'client=555002', $url))->assertForbidden();
});

it('odrzuca wygasły podpis', function () {
    config(['idosell.launch.ttl' => 30]);
    $url = Idosell::panelUrl('test.dashboard', [], $this->license);

    $this->travel(31)->minutes();

    $this->get($url)->assertForbidden();
});

it('odrzuca podpisany URL bez licencji', function () {
    $this->get(signedPanelUrl('test.dashboard', ['client' => 999999, 'application' => 4242]))
        ->assertForbidden();
});

it('odrzuca nieaktywną licencję', function () {
    IdosellLicense::factory()->inactive()->create(['client_id' => 555002]);

    $this->get(signedPanelUrl('test.dashboard', ['client' => 555002, 'application' => 4242]))
        ->assertForbidden();
});

it('odrzuca aplikację z URL inną niż aplikacja licencji', function () {
    $this->get(signedPanelUrl('test.dashboard', ['client' => 555001, 'application' => 9999]))
        ->assertForbidden();
});

it('odrzuca podpisany URL bez kontekstu sklepu', function () {
    $this->get(signedPanelUrl('test.dashboard', []))->assertForbidden();
});

it('zwraca JSON 403, gdy klient oczekuje JSON', function () {
    $this->getJson(route('test.dashboard', ['client' => 555001]))
        ->assertForbidden()
        ->assertExactJson(['message' => 'Link do panelu aplikacji jest nieprawidłowy lub wygasł. Uruchom aplikację ponownie z panelu sklepu.']);
});

it('przyjmuje POST na adres z panelUrl() bez cookies', function () {
    $url = Idosell::panelUrl('test.shops.fetch', [], $this->license);

    $this->post($url, ['shop' => 1])
        ->assertOk()
        ->assertJson(['client' => 555001]);
});

it('nie pozwala nadpisać kontekstu polem formularza', function () {
    IdosellLicense::factory()->create(['client_id' => 555002]);
    $url = Idosell::panelUrl('test.shops.fetch', [], $this->license);

    $this->post($url, ['client' => 555002])
        ->assertOk()
        ->assertJson(['client' => 555001]);
});

it('generuje przez idosell_route() link z kontekstem bieżącej licencji', function () {
    Route::get('/app/next', fn (): string => idosell_route('test.dashboard', ['tab' => 'ustawienia']))
        ->middleware('idosell.panel')
        ->name('test.next');

    $link = $this->get(signedPanelUrl('test.next', ['client' => 555001, 'application' => 4242]))
        ->assertOk()
        ->getContent();

    expect($link)->toContain('client=555001')
        ->and($link)->toContain('application=4242')
        ->and($link)->toContain('tab=ustawienia')
        ->and(URL::hasValidSignature(Request::create($link)))->toBeTrue();

    $this->get($link)->assertOk()->assertJson(['client' => 555001]);
});

it('panelUrl() bez licencji i bez kontekstu żądania rzuca wyjątek', function () {
    Idosell::panelUrl('test.dashboard');
})->throws(IdosellException::class);

it('currentLicense() zwraca null poza trasą panelu', function () {
    expect(Idosell::currentLicense())->toBeNull();
});
