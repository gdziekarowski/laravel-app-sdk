<?php

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Exceptions\ConfigurationException;
use Idosell\LaravelAppSdk\Services\AppsApiClient;
use Idosell\LaravelAppSdk\Services\SignatureService;
use Illuminate\Support\Facades\Http;

function appsApi(): AppsApiClient
{
    return new AppsApiClient(new SignatureService());
}

it('podpisuje każde żądanie i dokłada dane aplikacji', function () {
    Http::fake(['*installation/done' => Http::response(['status' => 'ok'], 200)]);
    Http::preventStrayRequests();

    appsApi()->confirmInstallation('LIC-TEST-0000000000000000');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://apps.idosell.com/api/application/installation/done'
            && $data['api_license'] === 'LIC-TEST-0000000000000000'
            && $data['application_id'] === 4242
            && $data['developer'] === 'dev-login'
            && $data['sign'] === app(SignatureService::class)->make();
    });
});

it('pomija puste filtry przy pobieraniu licencji', function () {
    Http::fake(['*application/license' => Http::response(['status' => 'ok', 'licenses' => []], 200)]);
    Http::preventStrayRequests();

    appsApi()->licenses(active: true);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $data['active'] === true && !array_key_exists('api_license', $data);
    });
});

it('wysyła narastającą kwotę w modelu usage/limit', function () {
    Http::fake(['*setPrice' => Http::response(['status' => 'ok'], 200)]);
    Http::preventStrayRequests();

    appsApi()->setPrice('LIC-TEST-0000000000000000', 250.50);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'application/license/setPrice')
        && $request->data()['price'] === 250.50);
});

it('traktuje status "error" w treści jako błąd, mimo HTTP 200', function () {
    Http::fake(['*application/license' => Http::response([
        'status' => 'error',
        'errors' => ['message' => 'Nieprawidłowy podpis'],
    ], 200)]);
    Http::preventStrayRequests();

    expect(fn () => appsApi()->licenses())
        ->toThrow(ApiException::class, 'Nieprawidłowy podpis');
});

it('zgłasza błąd HTTP z kodem statusu', function () {
    Http::fake(['*' => Http::response(['errors' => ['message' => 'Server error']], 500)]);
    Http::preventStrayRequests();

    config(['idosell.apps.retries' => 0]);

    expect(fn () => appsApi()->licenses())->toThrow(ApiException::class);
});

it('nie wywołuje API bez identyfikatora aplikacji', function () {
    config(['idosell.apps.application_id' => null]);

    Http::fake();
    Http::preventStrayRequests();

    expect(fn () => appsApi()->licenses())
        ->toThrow(ConfigurationException::class, 'idosell.apps.application_id');
});
