# Admin API sprzedawcy

Admin API to REST API **konkretnego panelu sklepu**. W aplikacji IdoSell Apps nie konfigurujesz
go ręcznie — adres i klucz przychodzą w webhooku instalacji i są zapisane w licencji.

## Klient

```php
$license = Idosell::license($clientId);
$client = $license->adminApi();

$client->get($client->admin('products/products'), ['resultsLimit' => 100]);
$client->post($client->admin('snippets/campaign'), ['params' => [...]]);
$client->put($client->admin('snippets/snippets'), ['params' => [...]]);
$client->delete($client->admin('snippets/snippets'), ['params' => [...]]);
```

`admin()` i `partners()` doklejają wersję bramki z configu:

```php
$client->admin('snippets/campaign');   // /admin/v8/snippets/campaign
$client->partners('offers/feed');      // /partners/v2/offers/feed
```

Podbicie wersji API to zmiana `IDOSELL_ADMIN_API_VERSION`, a nie przeszukiwanie kodu.

## Adres bazowy — dlaczego tylko host

`api_url` z webhooka identyfikuje panel sprzedawcy. SDK bierze z niego **wyłącznie host** i składa
`{scheme}://{host}/api`, bo ścieżki wywołań zawierają już prefiks bramki (`/admin/v8/...`,
`/partners/v2/...`).

Gdy `api_url` nie zawiera hosta, klient rzuca `ConfigurationException` zamiast strzelać w losowy adres.

## Autoryzacja

Dobierana z `authorization_type` licencji:

| `authorization_type` | Nagłówek |
|----------------------|----------|
| `key` (domyślnie) | `X-API-KEY: <klucz>` |
| `OAuth` | `Authorization: Bearer <token>` |

Przy OAuth w polu `api_key` trzyma się token dostępowy. Jego pobranie i odświeżanie
(`POST /authorize/accessToken`) jest po stronie aplikacji — SDK tylko wysyła to, co zapisane.

## Uprawnienia (gateways)

Zakres klucza ogranicza dostęp do obszarów API:

| Gateway | Obszar |
|---------|--------|
| `SYSTEM` | ustawienia systemowe |
| `CMS` | treści, snippety, odczyt klientów |
| `CRM` | relacje z klientami |
| `OMS` | zamówienia, zwroty, RMA |
| `PIM` | produkty i katalog |
| `WMS` | magazyn |

Przy zgłaszaniu aplikacji do publikacji deklarujesz potrzebne uprawnienia. Proś o **minimum** —
sprzedawcy to weryfikują, a szeroki zakres wydłuża weryfikację.

## Timeouty i ponowienia

Domyślnie: timeout 15 s, connect timeout 5 s, 3 ponowienia co 300 ms (`config/idosell.php`).

Dla wywołań blokujących interfejs skróć je — lepiej pokazać pusty formularz niż kazać czekać 45 s:

```php
$license->adminApi(timeout: 5, retries: 1);
```

## Obsługa błędów

```php
use Idosell\LaravelAppSdk\Exceptions\ApiException;

try {
    $client->get($client->admin('orders/orders'));
} catch (ApiException $e) {
    $e->status;   // kod HTTP (null przy błędzie zgłoszonym w treści)
    $e->errors;   // errors / faultString z odpowiedzi
}
```

Komunikat wyjątku **nie zawiera treści odpowiedzi** — payloady Admin API to dane sprzedawcy
i klientów końcowych (RODO). Trafia do niego tylko status i komunikat błędu z API.

### Wynik operacji w treści odpowiedzi

Część endpointów zwraca wynik biznesowy w treści odpowiedzi:

- `snippets/*` → `results[].errors`,
- `partners/v2 offers/feed` → `errors.faultString`,
- Apps API → `status: "error"`.

Zasoby SDK (`Snippets`, `OffersFeed`, `AppsApiClient`) sprawdzają te pola. Przy własnych wywołaniach
sprawdzaj treść odpowiedzi, zanim zapiszesz u siebie id zasobu.

## Paginacja

`POST /products/products/search` przyjmuje `resultsPage` (od 0) i `resultsLimit` (maks. 100).
Iteruj do wyczerpania, nie pobieraj wszystkiego naraz i nie loguj pobranych danych.

## Weryfikacja pól u źródła

Specyfikacja OpenAPI konkretnego sklepu: `https://{domena}/api/doc/admin/v8/json`.
Tam sprawdzaj dokładne nazwy i typy pól dla używanej wersji API.
