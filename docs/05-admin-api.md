# Admin API i Apps API

| API | Do czego | Adres | Klucz |
|-----|----------|-------|-------|
| **Admin API** | dane sklepu: produkty, zamówienia, snippety… | panel sprzedawcy, `/api/admin/v8/…` | klucz sprzedawcy z licencji |
| **API partnerskie** | feed ofertowy | panel sprzedawcy, `/api/partners/v2/…` | ten sam klucz sprzedawcy |
| **Apps API** | licencje i opłaty Twojej aplikacji | `apps.idosell.com` | podpis aplikacji (SDK dokłada sam) |

Admin API i API partnerskie obsługuje ten sam klient `$license->adminApi()` — różnią się tylko
prefiksem ścieżki: `$api->admin('…')` albo `$api->partners('…')`.

## Admin API sklepu

```php
$api = $license->adminApi();            // klient dla sklepu tego sprzedawcy

$api->get($api->admin('products/products'), ['resultsLimit' => 100]);     // GET z query stringiem
$api->post($api->admin('products/products/search'), ['params' => [...]]); // POST z JSON
$api->put($api->admin('snippets/snippets'), ['params' => [...]]);
$api->delete($api->admin('snippets/snippets'), ['params' => [...]]);
```

- `$api->admin('x')` buduje ścieżkę `/api/admin/v8/x` (wersja z `IDOSELL_ADMIN_API_VERSION`),
  `$api->partners('x')` — `/api/partners/v2/x` (wersja z `IDOSELL_PARTNERS_API_VERSION`).
- Metody zwracają zdekodowany JSON jako tablicę.
- Dostępne endpointy i pola: specyfikacja sklepu `https://{domena-sklepu}/api/doc/admin/v8/json`.
- Każda bramka API ma swoje uprawnienie. W panelu dewelopera zadeklaruj uprawnienia do bramek,
  z których korzysta aplikacja — bez nich API zwróci 401/403.
- Dla wywołań, które nie mogą blokować strony: `$license->adminApi(timeout: 5, retries: 0)`.

Zanim SDK wyśle klucz, sprawdza po kolei:

![Wywołanie Admin API](img/diagram-5.svg)

### Błędy

| Wyjątek | Co znaczy | Co zrobić |
|---------|-----------|-----------|
| `ApiException` | API zwróciło błąd (`$e->status`, `$e->errors`) | 401/403: brak uprawnień klucza — sprawdź uprawnienia w panelu dewelopera. Inne: pokaż komunikat, spróbuj później. |
| `DisallowedApiUrlException` | adres panelu sprzedawcy jest spoza listy dozwolonych domen (`$e->host`) lub nie jest bezpieczny | Jeśli to prawdziwy panel IdoSell z inną domeną — dopisz ją do `IDOSELL_ADMIN_API_ALLOWED_HOSTS`. |
| `IdosellException` | instalacja nie została potwierdzona w IdoSell | Licencja jest niekompletna — sprzedawca powinien zainstalować aplikację ponownie. |
| `ConfigurationException` | błąd w Twoim kodzie, np. pełny adres zamiast ścieżki | Używaj `$api->admin('…')`. |

Wszystkie dziedziczą po `Idosell\LaravelAppSdk\Exceptions\IdosellException`.

## Snippety (kod na stronach sklepu) — Admin API

Snippet to kawałek HTML/JS wstawiany na strony sklepu. Należy do kampanii. Kampanie i snippety SDK
wyszukuje po nazwie — **nie zmieniaj nazw**, bo zmiana utworzy nowy zasób.

```php
use Idosell\LaravelAppSdk\Facades\Idosell;

$snippets = Idosell::snippets($license);

$campaignId = $snippets->ensureCampaign('Moja aplikacja', shopId: 1);   // znajdź albo utwórz
$snippetId = $snippets->upsert($campaignId, 'Mój skrypt', '<script src="https://example.com/app.js" async></script>');
```

Opcjonalne ustawienia `upsert()` (wartości domyślne):

```php
$snippets->upsert($campaignId, 'Mój skrypt', $code, [
    'zone' => 'head',              // head | bodyBegin | bodyEnd
    'type' => 'html',              // html | javascript | cgi
    'active' => 'y',
    'pages' => ['all' => 'y'],
    'display' => ['clientType' => 'all', 'screen' => 'y', 'tablet' => 'y', 'phone' => 'y'],
]);
```

| Metoda | Działanie |
|--------|-----------|
| `ensureCampaign($name, $shopId)` | id kampanii — znajdź albo utwórz |
| `findCampaign($name, $shopId)` | id kampanii albo `null` |
| `deleteCampaign($campaignId)` | usuń kampanię |
| `upsert($campaignId, $name, $code, $options)` | id snippetu — utwórz albo zaktualizuj |
| `find($name, $campaignId)` | id snippetu albo `null` |
| `setEndDate($snippetId, 'Y-m-d' \| null)` | wyłącz snippet w danym dniu / anuluj |
| `delete($snippetId)` | usuń snippet |

Dane wstawiane do kodu snippetu (np. nazwy z bazy) zabezpieczaj przed XSS (`e()`, `json_encode()`).

## Feed ofertowy — API partnerskie

Feed korzysta z API partnerskiego (`partners/v2`), nie z Admin API — ten sam klucz sprzedawcy, inny
prefiks i wersja. Pola i dopuszczalne wartości: specyfikacja `partners/v2` sklepu.

Plik feedu generuje i hostuje IdoSell; aplikacja dostaje jego publiczny adres.

```php
use Idosell\LaravelAppSdk\Resources\OffersFeed;

$feeds = Idosell::offersFeed($license);

['id' => $feedId, 'url' => $url] = $feeds->ensure([
    'shop' => 1,
    'name' => 'Feed mojej aplikacji',
    'nameKey' => OffersFeed::nameKey(1),        // stały fragment publicznego adresu
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
]);
```

`ensure()` tworzy feed albo aktualizuje istniejący (po `shop` + `name`). Inne metody: `find()`, `disable()`,
`delete()`, `url()`. `nameKey()` wylicza się z `APP_KEY` — nie zmieniaj `APP_KEY`, bo zmienią się adresy feedów.

## Apps API

```php
$apps = Idosell::apps();

$apps->findLicense($license->api_license);     // dane licencji z IdoSell (aktywność, cennik, sklep)
$apps->licenses(active: true);                 // wszystkie aktywne licencje aplikacji
$apps->setPrice($license->api_license, 250);   // opłata za bieżący okres (opis niżej)
```

### Opłaty: `setPrice()`

Tylko dla aplikacji z modelem płatności **usage/limit** (cena zależna od użycia), ustawionym w panelu
dewelopera. Przy cenie stałej i subskrypcji IdoSell nalicza opłaty sam — `setPrice()` nie jest potrzebne.

Jak działa:

- **Ustawia** kwotę za bieżący okres rozliczeniowy sprzedawcy, **nie dodaje** jej: 100, a potem 250
  daje opłatę 250, nie 350. Wysyłaj zawsze **narastającą sumę** za cały okres.
- IdoSell dolicza kwotę do faktury sprzedawcy na koniec okresu.
- Sprzedawca przy instalacji ustala **limit wydatków** — kwoty powyżej limitu IdoSell odrzuci
  (`ApiException`).
- To prawdziwa opłata — testuj tylko na licencji w sklepie demo.

Przykład: 0,10 zł za każde zamówienie obsłużone przez aplikację w bieżącym okresie.

```php
use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Facades\Idosell;

// Np. w zadaniu uruchamianym raz dziennie, dla każdej aktywnej licencji.
$license = Idosell::license($clientId);

// Twoja logika: liczba zamówień od początku bieżącego okresu rozliczeniowego sprzedawcy.
$orders = $this->countHandledOrders($license, since: $periodStart);
$amount = round($orders * 0.10, 2);   // suma za CAŁY okres, nie przyrost od ostatniego wywołania

try {
    $result = Idosell::apps()->setPrice($license->api_license, $amount);
    // $result['license']: price, price_limit, currency, next_invoice_day
} catch (ApiException $e) {
    report($e);   // np. kwota powyżej limitu sprzedawcy
}
```

Odpowiedź zawiera `license.price` (ustawiona kwota), `license.price_limit` (limit sprzedawcy),
`license.currency` i `license.next_invoice_day` (dzień wystawienia faktury). Dane cennika licencji
(m.in. limit) zwraca też `findLicense()` w polu `pricing`.

## Model licencji i fasada — ściąga

```php
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;

Idosell::license($clientId);                   // licencja sprzedawcy (także nieaktywna) albo null
Idosell::currentLicense();                     // licencja w trasie panelu
IdosellLicense::query()->active()->get();      // wszystkie aktywne instalacje

$license->client_id;                           // numer sprzedawcy
$license->active;                              // czy aktywna
$license->shops();                             // [['id' => 1, 'name' => 'Sklep 1'], ...]
$license->domain();                            // domena panelu sprzedawcy
$license->adminApi();                          // klient Admin API
```

`api_key` i `api_license` są w bazie zaszyfrowane i ukryte w `toArray()`/JSON.
