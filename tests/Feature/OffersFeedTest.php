<?php

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Resources\OffersFeed;
use Illuminate\Support\Facades\Http;

/**
 * Atrapa `partners/v2 offers/feed`: GET zwraca listę feedów, POST/PUT — samo `id`
 * (bez adresu pliku), dokładnie jak prawdziwe API.
 *
 * @param  array<int, array<string, mixed>>  $feeds
 */
function fakeOffersFeedApi(array $feeds = [], int $writtenId = 3001): void
{
    Http::fake(function ($request) use ($feeds, $writtenId) {
        return $request->method() === 'GET'
            ? Http::response(['results' => $feeds], 200)
            : Http::response(['id' => $writtenId], 200);
    });

    Http::preventStrayRequests();
}

function feeds(): OffersFeed
{
    return Idosell::offersFeed(license());
}

$params = [
    'shop' => 1,
    'name' => 'Feed partnera',
    'format' => 'IOF30',
    'language' => 'pol',
    'currency' => 'PLN',
];

it('zakłada feed, gdy w panelu go nie ma', function () use ($params) {
    fakeOffersFeedApi(writtenId: 3001);

    $result = feeds()->ensure($params);

    expect($result['id'])->toBe(3001);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->data()['params']['active'] === 'yes'
        && $request->data()['params']['shop'] === 1);
});

it('aktualizuje istniejący feed zamiast zakładać drugi', function () use ($params) {
    fakeOffersFeedApi(feeds: [
        ['id' => 2002, 'shop' => 1, 'name' => 'Feed partnera', 'url' => 'https://demo-shop.example.com/feed.xml'],
    ], writtenId: 2002);

    $result = feeds()->ensure($params);

    expect($result['id'])->toBe(2002)
        ->and($result['url'])->toBe('https://demo-shop.example.com/feed.xml');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->data()['params']['id'] === 2002);

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('nie myli feedu innego sklepu', function () use ($params) {
    fakeOffersFeedApi(feeds: [
        ['id' => 2002, 'shop' => 2, 'name' => 'Feed partnera'],
    ], writtenId: 3003);

    expect(feeds()->ensure($params)['id'])->toBe(3003);

    Http::assertSent(fn ($request) => $request->method() === 'POST');
});

it('dociąga publiczny adres pliku osobnym zapytaniem', function () use ($params) {
    fakeOffersFeedApi(feeds: [
        ['id' => 3001, 'shop' => 1, 'name' => 'Feed partnera', 'url' => 'https://demo-shop.example.com/nowy.xml'],
    ], writtenId: 3001);

    expect(feeds()->ensure($params)['url'])->toBe('https://demo-shop.example.com/nowy.xml');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_contains($request->url(), 'id=3001'));
});

it('wyłącza feed bez kasowania, zachowując adres', function () use ($params) {
    fakeOffersFeedApi(writtenId: 3001);

    feeds()->disable(3001, $params);

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->data()['params']['active'] === 'no'
        && $request->data()['params']['id'] === 3001);
});

it('usuwa feed, podając id w query stringu', function () {
    fakeOffersFeedApi();

    feeds()->delete(3001);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/partners/v2/offers/feed?id=3001'));
});

it('zgłasza błąd, gdy API nie zwróciło id feedu', function () use ($params) {
    Http::fake(function ($request) {
        return $request->method() === 'GET'
            ? Http::response(['results' => []], 200)
            : Http::response(['errors' => ['faultCode' => 12, 'faultString' => 'Nieprawidłowy sterownik XSLT']], 200);
    });
    Http::preventStrayRequests();

    expect(fn () => feeds()->create($params))
        ->toThrow(ApiException::class, 'Nieprawidłowy sterownik XSLT');
});

it('generuje klucz nazwy stały dla sklepu i różny między sklepami', function () {
    expect(OffersFeed::nameKey(1))->toBe(OffersFeed::nameKey(1))
        ->and(OffersFeed::nameKey(1))->not->toBe(OffersFeed::nameKey(2))
        ->and(OffersFeed::nameKey(1))->toHaveLength(8);
});
