<?php

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Exceptions\ConfigurationException;
use Idosell\LaravelAppSdk\Services\AdminApiClient;
use Illuminate\Support\Facades\Http;

/**
 * Atrapa Admin API. Rejestrujemy ją per test — kolejne wywołania `Http::fake()`
 * dokładają reguły, a pierwsza pasująca wygrywa, więc wspólny fake w `beforeEach`
 * przesłoniłby przypadki błędów.
 *
 * @param  array<string, mixed>  $response
 */
function fakeAdminApi(array $response = ['results' => []], int $status = 200): void
{
    Http::fake(['*' => Http::response($response, $status)]);
    Http::preventStrayRequests();
}

it('buduje bazę API z samego hosta, niezależnie od kształtu api_url', function (string $apiUrl) {
    fakeAdminApi();

    (new AdminApiClient($apiUrl, 'klucz'))->get('/admin/v8/snippets/campaign');

    Http::assertSent(fn ($request) => $request->url()
        === 'https://demo-shop.example.com/api/admin/v8/snippets/campaign');
})->with([
    'sam host' => 'https://demo-shop.example.com',
    'z /api' => 'https://demo-shop.example.com/api',
    'z /api/admin' => 'https://demo-shop.example.com/api/admin',
    'ze slashem na końcu' => 'https://demo-shop.example.com/api/',
]);

it('zachowuje port, gdy panel działa na niestandardowym porcie', function () {
    fakeAdminApi();

    (new AdminApiClient('https://demo-shop.example.com:8443/api', 'klucz'))->get('/admin/v8/x');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://demo-shop.example.com:8443/api/'));
});

it('odmawia wywołania, gdy api_url nie zawiera hosta', function () {
    fakeAdminApi();

    expect(fn () => (new AdminApiClient('', 'klucz'))->get('/admin/v8/x'))
        ->toThrow(ConfigurationException::class);

    Http::assertNothingSent();
});

it('autoryzuje nagłówkiem X-API-KEY', function () {
    fakeAdminApi();

    (new AdminApiClient('https://demo-shop.example.com/api', 'tajny-klucz'))->get('/admin/v8/x');

    Http::assertSent(fn ($request) => $request->hasHeader('X-API-KEY', 'tajny-klucz')
        && !$request->hasHeader('Authorization'));
});

it('autoryzuje tokenem Bearer przy authorization_type = OAuth', function () {
    fakeAdminApi();

    (new AdminApiClient('https://demo-shop.example.com/api', 'token-oauth', AdminApiClient::AUTH_OAUTH))
        ->get('/admin/v8/x');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-oauth'));
});

it('składa ścieżki bramek z wersji w configu', function () {
    $client = new AdminApiClient('https://demo-shop.example.com/api', 'klucz');

    expect($client->admin('snippets/campaign'))->toBe('/admin/v8/snippets/campaign')
        ->and($client->partners('offers/feed'))->toBe('/partners/v2/offers/feed');

    config(['idosell.admin_api.version' => 'v9']);

    expect($client->admin('/products/products'))->toBe('/admin/v9/products/products');
});

it('przekazuje dane GET jako query string, a POST jako JSON', function () {
    fakeAdminApi();

    $client = new AdminApiClient('https://demo-shop.example.com/api', 'klucz');

    $client->get('/partners/v2/offers/feed', ['shop' => 7]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'offers/feed?shop=7'));

    $client->post('/admin/v8/snippets/campaign', ['params' => ['x' => 1]]);
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->data() === ['params' => ['x' => 1]]);
});

it('zamienia błąd HTTP na wyjątek z kodem statusu i komunikatem API', function () {
    config(['idosell.admin_api.retries' => 0]);

    fakeAdminApi([
        'errors' => ['faultCode' => 4, 'faultString' => 'Brak uprawnień do bramki PIM'],
    ], 403);

    try {
        (new AdminApiClient('https://demo-shop.example.com/api', 'klucz'))
            ->get('/admin/v8/products/products');

        $this->fail('Oczekiwano ApiException.');
    } catch (ApiException $e) {
        expect($e->status)->toBe(403)
            ->and($e->getMessage())->toContain('Brak uprawnień do bramki PIM')
            ->and($e->errors)->toBe(['faultCode' => 4, 'faultString' => 'Brak uprawnień do bramki PIM']);
    }
});

it('tworzy klienta z licencji sprzedawcy', function () {
    fakeAdminApi();

    license(['api_url' => 'https://inny-panel.example.com/api', 'api_key' => 'klucz-z-licencji'])
        ->adminApi()
        ->get('/admin/v8/x');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://inny-panel.example.com/api/')
        && $request->hasHeader('X-API-KEY', 'klucz-z-licencji'));
});
