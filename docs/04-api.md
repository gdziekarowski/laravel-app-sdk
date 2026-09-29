# API

## Fasada `Idosell`

| Metoda | Zwraca |
|--------|--------|
| `license(int $clientId, ?int $applicationId = null)` | `?IdosellLicense`, także nieaktywną; aplikacja domyślnie z configu |
| `currentLicense()` | `?IdosellLicense` bieżącego żądania panelu |
| `panelUrl(string $route, array $parameters = [], ?IdosellLicense $license = null)` | podpisany link panelu |
| `adminApi(IdosellLicense $license, ?int $timeout = null, ?int $retries = null)` | `AdminApiClient` |
| `snippets(IdosellLicense $license)` | `Resources\Snippets` |
| `offersFeed(IdosellLicense $license)` | `Resources\OffersFeed` |
| `apps()` | `Services\AppsApiClient` |
| `sign(?string $date = null)` / `verify(string $sign)` | podpis `sign` / `bool` |
| `decryptApiKey(string $encrypted)` | odszyfrowany `api_key` |
| `resolveLaunchUrlUsing(?Closure $callback)` | `void`, zob. [02-panel.md](02-panel.md) |

Helper `idosell_route(string $name, array $parameters = [], ?IdosellLicense $license = null)` = `panelUrl()`.

## Model `IdosellLicense`

`Idosell\LaravelAppSdk\Models\IdosellLicense`, jeden rekord na parę (`client_id`, `application_id`).

| Pole | Typ | |
|------|-----|---|
| `client_id`, `application_id` | `int` | |
| `api_url` | `string` | adres panelu sprzedawcy |
| `api_key` | `?string` | klucz Admin API albo token OAuth; szyfrowany, ukryty w `toArray()` |
| `api_license` | `string` | klucz licencji; szyfrowany, ukryty w `toArray()` |
| `authorization_type` | `string` | `key` albo `OAuth` |
| `active` | `bool` | |
| `installation_confirmed` | `bool` | |
| `meta` | `?array` | `selected_shops`, `contact_data` (dane osobowe sprzedawcy) |

```php
$license->shops();       // [['id' => 1, 'name' => 'Sklep 1'], ...]
$license->domain();      // 'sklep.iai-shop.com'
$license->adminApi();    // AdminApiClient

IdosellLicense::query()->active()->forClient($clientId)->forApplication()->first();
IdosellLicense::query()->active()->get();
```

## Admin API sprzedawcy

```php
use Idosell\LaravelAppSdk\Exceptions\ApiException;

$api = $license->adminApi();                           // albo adminApi(timeout: 5, retries: 1)

$api->get($api->admin('products/products'), ['resultsLimit' => 100]);    // query string
$api->post($api->admin('products/products/search'), ['params' => [...]]); // JSON
$api->put($api->admin('snippets/snippets'), ['params' => [...]]);
$api->delete($api->admin('snippets/snippets'), ['params' => [...]]);
$api->get($api->partners('offers/feed'), ['shop' => 1]);
```

- `admin('x')` → `/api/admin/v8/x`, `partners('x')` → `/api/partners/v2/x` (wersje z configu).
- Host z `api_url` licencji, nagłówek z `authorization_type`: `X-API-KEY` albo `Authorization: Bearer`.
- Zwracają zdekodowany JSON (`array`).
- Błąd HTTP: `ApiException` z `$e->status` i `$e->errors`. `api_url` bez hosta: `ConfigurationException`.
- Pola endpointów: specyfikacja sklepu `https://{domena}/api/doc/admin/v8/json`.

Uprawnienia klucza (deklarowane przy zgłoszeniu aplikacji): `SYSTEM`, `CMS` (m.in. snippety), `CRM`,
`OMS`, `PIM` (produkty, feed), `WMS`.

## Snippety

Kod HTML/JS na stronach sklepu. Snippet należy do kampanii. Kampanie i snippety są wyszukiwane po
nazwie, więc nazwy traktuj jak identyfikatory: zmiana nazwy utworzy nowy zasób.

```php
$snippets = Idosell::snippets($license);

$campaignId = $snippets->ensureCampaign('Moja aplikacja', shopId: 1);   // znajdź albo utwórz
$snippetId = $snippets->upsert($campaignId, 'Mój skrypt', '<script src="https://example.com/app.js" async></script>', [
    'zone' => 'head',              // head | bodyBegin | bodyEnd
    'type' => 'html',              // html | javascript | cgi
    'lang' => 'pol',
    'active' => 'y',
    'pages' => ['all' => 'y'],
    'display' => ['clientType' => 'all', 'screen' => 'y', 'tablet' => 'y', 'phone' => 'y'],
    'extra' => [],                 // dodatkowe pola snippetu
]);
```

Wszystkie opcje `upsert()` są opcjonalne; wartości powyżej to domyślne.

| Metoda | Zwraca |
|--------|--------|
| `campaigns()` | lista kampanii |
| `findCampaign(string $name, ?int $shopId = null)` | `?int` id |
| `createCampaign(string $name, ?int $shopId = null, array $overrides = [])` | `int` id |
| `ensureCampaign(string $name, ?int $shopId = null)` | `int` id |
| `deleteCampaign(int $campaignId)` | `void` |
| `all()` | lista snippetów |
| `find(string $name, int $campaignId)` | `?int` id |
| `upsert(int $campaignId, string $name, string $code, array $options = [])` | `int` id |
| `setEndDate(int $snippetId, ?string $date)` | `void`; `'Y-m-d'` wyłącza snippet tego dnia, `null` anuluje |
| `delete(int $snippetId)` | `void` |

Błąd zwrócony przez API: `ApiException`.

## Feed ofertowy

Plik feedu generuje i hostuje IdoSell (`partners/v2 offers/feed`); aplikacja dostaje publiczny adres.
Format bazowy IOF 3.0, transformacja do formatu odbiorcy przez XSLT w polu `driver`.

```php
use Idosell\LaravelAppSdk\Resources\OffersFeed;

$feeds = Idosell::offersFeed($license);

$params = [
    'shop' => 1,
    'name' => 'Feed mojej aplikacji',
    'nameKey' => OffersFeed::nameKey(1),
    'format' => 'IOF30',
    'driver' => file_get_contents(resource_path('feed/driver.xsl')),   // Twój arkusz XSLT
    'description' => 'own',
    'price' => 'own',
    'picture' => 'all',
    'priceDifference' => 0,
    'accessSetting' => 'unlimited',
    'language' => 'pol',
    'currency' => 'PLN',
    'availability' => 'all',
];

['id' => $feedId, 'url' => $url] = $feeds->ensure($params);   // utwórz albo zaktualizuj (po shop + name)
```

Pola i dopuszczalne wartości: specyfikacja `partners/v2` sklepu.

| Metoda | Zwraca |
|--------|--------|
| `all(?int $shopId = null)` | lista feedów |
| `find(int $shopId, string $name)` | `?array` feed |
| `create(array $params)` | `int` id |
| `update(int $feedId, array $params, string $active = 'yes')` | `int` id |
| `ensure(array $params)` | `['id' => int, 'url' => ?string]`; `$params` musi mieć `shop` i `name` |
| `disable(int $feedId, array $params)` | `int` id; wyłącza feed, adres zostaje (te same `$params` co przy tworzeniu) |
| `delete(int $feedId)` | `void` |
| `url(int $feedId)` | `?string` publiczny adres; `null`, jeśli jeszcze niedostępny |
| `OffersFeed::nameKey(int $shopId, string $scope = 'offers-feed')` | 8 znaków, stałe dla sklepu (z `APP_KEY`) |

`nameKey` wchodzi do publicznego adresu pliku. Używaj `nameKey()`, żeby adres się nie zmieniał.

## Apps API

```php
$apps = Idosell::apps();

$apps->licenses(active: true);                 // lista licencji aplikacji
$apps->licenses(apiLicense: $license->api_license);
$apps->setPrice($license->api_license, 250);   // model usage/limit
$apps->post('/application/...', [...]);        // dowolny endpoint; sign dokładany automatycznie
```

`setPrice()` ustawia kwotę bieżącego okresu rozliczeniowego, nie dolicza: 100, potem 250 daje 250.
Wysyłaj narastającą sumę okresu i nie przekraczaj limitu wydatków sprzedawcy.

`installation/done` pakiet wywołuje sam przy aktywacji aplikacji `online`.

Błąd HTTP albo `status: "error"` w odpowiedzi: `ApiException`.
