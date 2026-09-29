# Wywołania Admin API przez `idosell/laravel-app-sdk`

## Klient

Adres Admin API bierze się **z licencji sprzedawcy**, nie z konfiguracji aplikacji — jedna instalacja
aplikacji = jeden panel.

```php
$client = $license->adminApi();            // albo Idosell::adminApi($license)

$client->get($client->admin('products/products'), ['resultsLimit' => 100]);
$client->post($client->admin('snippets/campaign'), ['params' => [...]]);
$client->get($client->partners('offers/feed'), ['shop' => 1]);
```

`admin()` i `partners()` doklejają wersję bramki z `config('idosell.admin_api.*')`, więc podbicie
wersji to zmiana jednej wartości w configu, a nie w kilkunastu miejscach w kodzie.

Autoryzacja dobierana jest z `authorization_type` licencji: `X-API-KEY` albo `Authorization: Bearer`.

Timeout, connect timeout i retry są ustawione domyślnie. Dla wywołań blokujących UI skróć je:
`$license->adminApi(timeout: 5, retries: 1)`.

## Bazowy URL — dlaczego tylko host

`api_url` z webhooka identyfikuje panel sprzedawcy. SDK bierze z niego **wyłącznie host** i skleja
`{scheme}://{host}/api`, bo ścieżki wywołań zawierają już `/admin/v{X}` lub `/partners/v{X}`.

## Snippety (wstrzykiwanie kodu na stronach sklepu)

```php
$snippets = Idosell::snippets($license);

$campaignId = $snippets->ensureCampaign('Moja kampania', shopId: 1);
$snippetId = $snippets->upsert($campaignId, 'Mój pixel', '<script src="..."></script>');
```

- `ensureCampaign()` i `upsert()` szukają zasobu **po tożsamości** (nazwa + sklep/kampania), nie po
  zapamiętanym id. Panel sprzedawcy jest źródłem prawdy: ręczne skasowanie zasobu zostanie wykryte
  i naprawione, a wielokrotna instalacja nie namnoży duplikatów.
- `display` musi być ustawione jawnie — bez tego API domyślnie ustawia `n` i snippet się nie ładuje.
- `setEndDate($snippetId, '2026-12-31')` zleca IdoSellowi wyłączenie snippetu w danym dniu.
  To najprostszy sposób na karencję: działa, nawet gdy klucz API już wygaśnie.

## Feed ofertowy (`partners/v2 offers/feed`)

```php
$feeds = Idosell::offersFeed($license);

['id' => $id, 'url' => $url] = $feeds->ensure([
    'shop' => 1,
    'name' => 'Mój feed',
    'nameKey' => OffersFeed::nameKey(1),   // stały per sklep → stały URL pliku
    'format' => 'IOF30',
    'driver' => $xslt,                     // sterownik XSLT inline
    // ...
]);
```

- Plik generuje, hostuje i odświeża IdoSell — nie buduj własnego XML-a.
- `POST`/`PUT` zwracają samo `id`; adres pliku dociąga `url()` osobnym `GET`-em.
- `nameKey` **musi być stały dla sklepu**, inaczej każda aktualizacja zmienia publiczny URL feedu.
- Odłączenie: `disable()` (zostaje ten sam URL) albo `delete()`.

## Obsługa błędów

Nieudane wywołanie rzuca `Exceptions\ApiException` z `status` i `errors`. Komunikat celowo nie
zawiera treści odpowiedzi — payloady Admin API to dane sprzedawcy i klientów końcowych (RODO).

Wynik operacji jest w treści odpowiedzi: `snippets/*` zwraca `results[].errors`, a `partners/v2`
`errors.faultString`. Zasoby SDK sprawdzają te pola; przy własnych wywołaniach sprawdzaj je sam.

## Bezpieczeństwo

- Klucz z minimalnymi uprawnieniami (gateways) i whitelistą IP.
- Pobieraj tylko potrzebne pola; nie loguj danych osobowych ani całych payloadów.
