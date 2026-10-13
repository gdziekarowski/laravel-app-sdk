# Wywołania Admin API przez `idosell/laravel-app-sdk`

Pełna dokumentacja: `vendor/idosell/laravel-app-sdk/docs/05-admin-api.md`.

## Klient

Adres Admin API bierze się **z licencji sprzedawcy** (jedna instalacja = jeden panel), nie z configu.

```php
use Idosell\LaravelAppSdk\Facades\Idosell;

$api = $license->adminApi();                         // albo Idosell::adminApi($license)

$api->get($api->admin('system/shopsData'));                                // GET + query string
$api->get($api->admin('products/products'), ['resultsLimit' => 100]);
$api->post($api->admin('products/products/search'), ['params' => [/* … */]]);
$api->put($api->admin('snippets/snippets'), ['params' => [/* … */]]);
$api->delete($api->admin('snippets/snippets'), ['params' => [/* … */]]);
$api->get($api->partners('offers/feed'), ['shop' => 1]);
```

- `admin('x')` → `/api/admin/v8/x`, `partners('x')` → `/api/partners/v2/x` (wersje z
  `IDOSELL_ADMIN_API_VERSION` / `IDOSELL_PARTNERS_API_VERSION`). Metody zwracają zdekodowany JSON (tablica).
- Autoryzacja z `authorization_type` licencji: `X-API-KEY` albo `Authorization: Bearer`.
- Domyślne timeouty i retry z configu; dla wywołań blokujących UI:
  `$license->adminApi(timeout: 5, retries: 0)`.
- Licencję w panelu aplikacji dostajesz z `idosell.panel` (`IdosellLicense $license`,
  `Idosell::currentLicense()`), w tle — `Idosell::license($clientId)`.

**Rób:**
- Zawsze ścieżki względne przez `$api->admin('…')` / `$api->partners('…')`.
- Wywołania z licencji aktywnej i potwierdzonej; w jobach sprawdzaj `$license->active`.
- Ciężkie/masowe operacje w kolejce, z paginacją.

**Nie rób:**
- Własnego `Http::withHeaders(['X-API-KEY' => …])` ani budowania URL z `api_url` — omija politykę
  adresów i blokadę przekierowań.
- Pełnych URL-i w ścieżce (`https://…`, `//…`) → `ConfigurationException`.
- Hardkodowania `v8` / `v2` w ścieżkach.
- `verify => false` / wyłączania TLS w kliencie HTTP.

## Co SDK sprawdza przed wysłaniem klucza

- Licencja online musi mieć potwierdzone `installation/done` — inaczej `adminApi()` rzuca
  `IdosellException` („Admin API niedostępne”).
- `api_url` licencji: https, publiczna domena (bez IP, `localhost`, domen wewnętrznych), host z listy
  `IDOSELL_ADMIN_API_ALLOWED_HOSTS` (domyślnie `*.idosell.com,*.iai-shop.com`) — inaczej
  `DisallowedApiUrlException` (`$e->reason`: `scheme|not_public|not_allowed|invalid`, `$e->host`).
- Odpowiedź 3xx nie jest śledzona → `ApiException`.

## Snippety

```php
$snippets = Idosell::snippets($license);

$campaignId = $snippets->ensureCampaign('Moja aplikacja', shopId: 1);   // znajdź albo utwórz
$snippetId = $snippets->upsert($campaignId, 'Mój skrypt', '<script src="https://example.com/app.js" async></script>', [
    'zone' => 'head',            // head | bodyBegin | bodyEnd (domyślnie head)
    'type' => 'html',            // html | javascript | cgi (domyślnie html)
]);
```

| Metoda | Działanie |
|--------|-----------|
| `ensureCampaign($name, $shopId)` / `findCampaign()` / `createCampaign()` / `deleteCampaign($id)` | kampanie |
| `upsert($campaignId, $name, $code, $options)` | utwórz albo zaktualizuj; zwraca id |
| `find($name, $campaignId)` / `all()` / `delete($id)` | snippety |
| `setEndDate($snippetId, 'Y-m-d' \| null)` | IdoSell wyłączy snippet w danym dniu / anuluj |

- Zasoby są wyszukiwane **po nazwie** (+ sklep / kampania), nie po zapamiętanym id — ponowna
  instalacja nie tworzy duplikatów, ręcznie skasowany zasób wraca. **Nie zmieniaj nazw** między
  wersjami (zmiana = nowy zasób) i nie zapisuj id jako źródła prawdy.
- `upsert()` zawsze ustawia `display` (bez niego API ustawia `n` i snippet się nie ładuje).
- `setEndDate()` to karencja niezależna od ważności klucza — np. w `LicenseDeactivating`.
- Dane wstawiane do kodu snippetu escapuj (`e()`, `json_encode()`).

## Feed ofertowy (`partners/v2 offers/feed`)

```php
use Idosell\LaravelAppSdk\Resources\OffersFeed;

$params = [
    'shop' => 1,
    'name' => 'Feed mojej aplikacji',
    'nameKey' => OffersFeed::nameKey(1),   // stały per sklep → stały URL pliku
    'format' => 'IOF30',
    'driver' => file_get_contents(resource_path('feed/driver.xsl')),
    // description, price, picture, accessSetting, language, currency, availability…
];

['id' => $feedId, 'url' => $url] = Idosell::offersFeed($license)->ensure($params);
```

- `ensure()` szuka po `shop` + `name` i robi `PUT` albo `POST`; `url` dociąga osobnym `GET`
  (best-effort, może być `null`).
- Inne: `all(?$shopId)`, `find($shopId, $name)`, `create()`, `update($id, $params)`,
  `disable($id, $params)` (URL zostaje), `delete($id)`, `url($id)`.
- `nameKey()` wylicza się z `APP_KEY` — zmiana `APP_KEY` zmieni publiczne adresy feedów.
- Nie buduj własnego XML-a; plik generuje i odświeża IdoSell.

## Obsługa błędów

| Wyjątek | Znaczenie |
|---------|-----------|
| `ApiException` (`$e->status`, `$e->errors`) | HTTP ≠ 2xx, 3xx, albo błąd w treści przy 200 (`results[].errors` w snippetach, `errors.faultString` w `partners/v2`). 401/403 → brak bramki w panelu dewelopera. |
| `DisallowedApiUrlException` | `api_url` poza polityką; prawdziwy panel z inną domeną → dopisz ją do `IDOSELL_ADMIN_API_ALLOWED_HOSTS`. |
| `IdosellException` | licencja online bez `installation/done` — sprzedawca instaluje ponownie. |
| `ConfigurationException` | błąd w kodzie (np. pełny URL zamiast ścieżki) albo brak konfiguracji. |

Wszystkie dziedziczą po `Idosell\LaravelAppSdk\Exceptions\IdosellException`. Komunikat
`ApiException` celowo nie zawiera treści odpowiedzi (RODO).

Zasoby SDK (`Snippets`, `OffersFeed`) sprawdzają błędy w treści same. Przy własnych wywołaniach
(`$api->post(...)`) sprawdzaj je sam — HTTP 200/207 nie oznacza sukcesu.

## Bezpieczeństwo

- Deklaruj minimum bramek w panelu dewelopera.
- Pobieraj tylko potrzebne pola; nie loguj odpowiedzi Admin API ani danych osobowych.
- Nie filtruj webhooków IdoSell po IP — IP nadawców nie są stałe.
