<?php

namespace Idosell\LaravelAppSdk\Resources;

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Services\AdminApiClient;

/**
 * Feed ofertowy sklepu (`partners/v2 offers/feed`) — inny prefiks niż Admin API,
 * ale ten sam host i ta sama autoryzacja.
 *
 * Plik feedu generuje, hostuje i odświeża IdoSell; aplikacja dostaje stały publiczny
 * URL. Format bazowy to IOF 3.0, a transformację do docelowego schematu robi sterownik
 * XSLT przekazywany inline w polu `driver`. Nie buduj feedu samodzielnie — utrzymanie
 * własnego generatora i świeżości danych jest znacznie droższe.
 *
 * `POST`/`PUT` zwracają samo `id`, bez adresu pliku — URL dociąga się osobnym `GET`.
 *
 * @see https://idosell.readme.io/reference
 */
class OffersFeed
{
    public function __construct(private readonly AdminApiClient $client) {}

    /**
     * Lista feedów; opcjonalnie zawężona do sklepu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(?int $shopId = null): array
    {
        $query = $shopId !== null ? ['shop' => $shopId] : [];

        return (array) ($this->client->get($this->client->partners('offers/feed'), $query)['results'] ?? []);
    }

    /**
     * Szuka feedu po tożsamości (sklep + nazwa) — nie po zapamiętanym id.
     *
     * Dzięki temu ręczne skasowanie feedu w panelu jest wykrywane i feed powstaje od nowa,
     * zamiast wiecznie odbijać się o 404.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $shopId, string $name): ?array
    {
        foreach ($this->all($shopId) as $feed) {
            if ((int) ($feed['shop'] ?? 0) === $shopId
                && ($feed['name'] ?? null) === $name
                && !empty($feed['id'])) {
                return $feed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $params  pola feedu (bez `id`), zob. dokumentację partners/v2
     * @return int id feedu
     */
    public function create(array $params): int
    {
        return $this->resultId(
            $this->client->post($this->client->partners('offers/feed'), [
                'params' => $params + ['active' => 'yes'],
            ]),
        );
    }

    /**
     * Aktualizacja feedu — służy też do ponownej aktywacji (`active: yes`).
     *
     * @param  array<string, mixed>  $params
     */
    public function update(int $feedId, array $params, string $active = 'yes'): int
    {
        return $this->resultId(
            $this->client->put($this->client->partners('offers/feed'), [
                'params' => ['id' => $feedId] + $params + ['active' => $active],
            ]),
        );
    }

    /**
     * Tworzy albo aktualizuje feed sklepu i zwraca jego id oraz publiczny URL.
     *
     * @param  array<string, mixed>  $params  musi zawierać `shop` i `name`
     * @return array{id: int, url: string|null}
     */
    public function ensure(array $params): array
    {
        $shopId = (int) ($params['shop'] ?? 0);
        $existing = $this->find($shopId, (string) ($params['name'] ?? ''));

        $id = $existing !== null
            ? $this->update((int) $existing['id'], $params)
            : $this->create($params);

        return ['id' => $id, 'url' => $this->url($id)];
    }

    /**
     * Wyłącza feed bez kasowania — po ponownej aktywacji URL pozostaje ten sam.
     *
     * @param  array<string, mixed>  $params
     */
    public function disable(int $feedId, array $params): int
    {
        return $this->update($feedId, $params, 'no');
    }

    public function delete(int $feedId): void
    {
        // Identyfikator feedu przekazujemy w query stringu, nie w ciele żądania.
        $this->client->delete($this->client->partners('offers/feed').'?id='.$feedId);
    }

    /**
     * Publiczny adres pliku feedu. Best-effort — brak URL nie znaczy, że feed nie istnieje.
     */
    public function url(int $feedId): ?string
    {
        foreach ((array) ($this->client->get($this->client->partners('offers/feed'), ['id' => $feedId])['results'] ?? []) as $feed) {
            if ((int) ($feed['id'] ?? 0) === $feedId && !empty($feed['url'])) {
                return (string) $feed['url'];
            }
        }

        return null;
    }

    /**
     * Deterministyczny klucz feedu (max 8 znaków — ograniczenie panelu), z którego IdoSell
     * buduje nazwę pliku.
     *
     * Musi być STAŁY dla sklepu, inaczej każda aktualizacja zmieniałaby publiczny URL
     * feedu — a ten bywa przekazany systemom zewnętrznym.
     */
    public static function nameKey(int $shopId, string $scope = 'offers-feed'): string
    {
        return substr(md5(config('app.key').'|'.$scope.'|'.$shopId), 0, 8);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function resultId(array $response): int
    {
        $id = $response['id'] ?? null;

        if (empty($id) || !empty($response['errors'])) {
            throw ApiException::fromPayload(
                'utworzenie/aktualizacja feedu ofertowego',
                is_array($response['errors'] ?? null) ? $response['errors'] : [],
            );
        }

        return (int) $id;
    }
}
