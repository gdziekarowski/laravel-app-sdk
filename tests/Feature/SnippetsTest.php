<?php

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Resources\Snippets;
use Illuminate\Support\Facades\Http;

/**
 * Atrapa endpointów snippetów: `$campaigns`/`$snippets` to zawartość panelu,
 * a POST/PUT zwracają `results[0].id`, tak jak robi to Admin API.
 *
 * @param  array<int, array<string, mixed>>  $campaigns
 * @param  array<int, array<string, mixed>>  $snippets
 */
function fakeSnippetsApi(array $campaigns = [], array $snippets = [], int $createdId = 4001): void
{
    Http::fake(function ($request) use ($campaigns, $snippets, $createdId) {
        $isCampaign = str_contains($request->url(), 'snippets/campaign');

        if ($request->method() === 'GET') {
            return Http::response(['results' => $isCampaign ? $campaigns : $snippets], 200);
        }

        return Http::response(['results' => [['id' => $createdId]]], 200);
    });

    Http::preventStrayRequests();
}

function snippets(): Snippets
{
    return Idosell::snippets(license());
}

it('tworzy kampanię, gdy w panelu jeszcze jej nie ma', function () {
    fakeSnippetsApi(createdId: 501);

    expect(snippets()->ensureCampaign('Moja kampania', 1))->toBe(501);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'snippets/campaign')
        && $request->data()['params']['campaigns'][0]['shop'] === [1]);
});

it('używa istniejącej kampanii zamiast tworzyć duplikat', function () {
    fakeSnippetsApi(campaigns: [
        ['id' => 77, 'name' => 'Moja kampania', 'shop' => [1]],
    ]);

    expect(snippets()->ensureCampaign('Moja kampania', 1))->toBe(77);

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('odróżnia kampanie po sklepie', function () {
    fakeSnippetsApi(campaigns: [
        ['id' => 77, 'name' => 'Moja kampania', 'shop' => [2]],
    ], createdId: 78);

    // Kampania o tej nazwie istnieje, ale dla innego sklepu — dla sklepu 1 trzeba założyć nową.
    expect(snippets()->ensureCampaign('Moja kampania', 1))->toBe(78);
});

it('tworzy snippet w kampanii z wymuszoną widocznością na urządzeniach', function () {
    fakeSnippetsApi(createdId: 9001);

    expect(snippets()->upsert(77, 'Pixel', '<script src="https://example.com/p.js"></script>'))->toBe(9001);

    Http::assertSent(function ($request) {
        if ($request->method() !== 'POST' || !str_contains($request->url(), 'snippets/snippets')) {
            return false;
        }

        $snippet = $request->data()['params']['snippets'][0];

        return $snippet['campaign'] === 77
            && $snippet['zone'] === 'head'
            && $snippet['active'] === 'y'
            && $snippet['pages'] === ['all' => 'y']
            && $snippet['display']['screen'] === 'y'
            && !array_key_exists('id', $snippet);
    });
});

it('aktualizuje istniejący snippet zamiast dokładać kolejny', function () {
    fakeSnippetsApi(snippets: [
        ['id' => 9001, 'name' => 'Pixel', 'campaign' => 77],
    ]);

    snippets()->upsert(77, 'Pixel', '<script>nowa wersja</script>');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->data()['params']['snippets'][0]['id'] === 9001);

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('pozwala nadpisać strefę i typ snippetu', function () {
    fakeSnippetsApi();

    snippets()->upsert(77, 'Skrypt', 'console.log(1)', ['zone' => 'bodyEnd', 'type' => 'javascript']);

    Http::assertSent(function ($request) {
        $snippet = $request->data()['params']['snippets'][0] ?? null;

        return $snippet !== null
            && $snippet['zone'] === 'bodyEnd'
            && $snippet['type'] === 'javascript';
    });
});

it('ustawia natywną datę wyłączenia snippetu', function () {
    fakeSnippetsApi();

    snippets()->setEndDate(9001, '2026-12-31');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->data()['params']['snippets'][0]['dateEnd'] === ['defined' => 'y', 'date' => '2026-12-31']);
});

it('czyści datę wyłączenia snippetu', function () {
    fakeSnippetsApi();

    snippets()->setEndDate(9001, null);

    Http::assertSent(fn ($request) => $request->data()['params']['snippets'][0]['dateEnd']
        === ['defined' => 'n', 'date' => null]);
});

it('nie uznaje odpowiedzi z błędem walidacji za sukces', function () {
    Http::fake(function ($request) {
        return $request->method() === 'GET'
            ? Http::response(['results' => []], 200)
            : Http::response(['results' => [['id' => null, 'errors' => ['faultString' => 'Brak pola body']]]], 200);
    });
    Http::preventStrayRequests();

    expect(fn () => snippets()->upsert(77, 'Pixel', ''))
        ->toThrow(ApiException::class, 'Brak pola body');
});
