# Integracja przez `idosell/laravel-app-sdk` (3.x)

Większość mechanizmów IdoSell Apps jest w paczce. Pisz panel aplikacji i listenery — nie
infrastrukturę. Pełna dokumentacja: `vendor/idosell/laravel-app-sdk/docs/` (01–08).

## Co daje paczka

| Element | Gdzie |
|---------|-------|
| Webhooki `new-license`, `launch`, `remove-license` (trasy, walidacja, odpowiedzi z `sign`) | `Http\Controllers\WebhookController`, prefiks `api/idosell/webhooks` |
| Weryfikacja `sign` / log webhooków z redakcją | middleware `idosell.verify-sign`, `idosell.log-webhook` |
| Podpis, deszyfracja klucza | `Idosell::sign()`, `Idosell::verify($sign)`, `Idosell::decryptApiKey($enc)` |
| Licencje (sekrety szyfrowane, ukryte w `toArray()`) | `Models\IdosellLicense`, `Idosell::license($clientId)` |
| Panel w iframe | middleware `idosell.panel`, `idosell_route()`, `Idosell::panelUrl()`, `Idosell::currentLicense()` |
| Admin API / API partnerskie | `$license->adminApi()` → `admin('…')`, `partners('…')` |
| Snippety, feed ofertowy | `Idosell::snippets($license)`, `Idosell::offersFeed($license)` |
| Apps API | `Idosell::apps()` → `findLicense()`, `licenses()`, `setPrice()` |
| Zdarzenia | `Events\LicenseActivated`, `LicenseDeactivating`, `LicenseDeactivated`, `AppLaunched` |
| Polityka adresów paneli | `Support\ApiUrlPolicy`, `Exceptions\DisallowedApiUrlException` |
| Redakcja logów | `Support\Redactor::redact($data)` |
| Komendy | `idosell:install`, `idosell:doctor [--live]`, `idosell:licenses`, `idosell:simulate` |
| Testy | `Testing\InteractsWithIdosell`, `IdosellLicense::factory()` |

## Czego NIE pisz od nowa

- Liczenia `sha256(developer|data|klucz)` ani `openssl_decrypt` z `/keyset`.
- Kontrolera / tras webhooków licencji, własnej weryfikacji `client_id` czy `api_license`.
- Wywołania `installation/done` — SDK robi to samo w trakcie webhooka `new-license`.
- Tabeli na klucze sprzedawców — jest `idosell_licenses`.
- Klienta HTTP do Admin API (`Http::withHeaders(['X-API-KEY' => …])`) — używaj `$license->adminApi()`.
- Własnego mechanizmu sesji/tokenów dla panelu — panel identyfikuje podpisany link.

## Konfiguracja

```dotenv
IDOSELL_APPLICATION_ID=1234
IDOSELL_DEVELOPER=twoj-login
IDOSELL_APPLICATION_KEY=…        # SEKRET — nigdy w repo
IDOSELL_LAUNCH_ROUTE=app.panel   # nazwa trasy panelu
```

- `IDOSELL_APP_TYPE=online` (domyślnie) dla aplikacji w iframe.
- `IDOSELL_VERIFY_LICENSE_OWNER=true` (domyślnie) — nie wyłączaj na produkcji (tylko lokalnie albo
  awaryjnie przy awarii/zmianie formatu Apps API).
- `IDOSELL_ADMIN_API_ALLOWED_HOSTS=*.idosell.com,*.iai-shop.com` (domyślnie) — dopisuj tylko
  konkretne domeny paneli IdoSell, nigdy obcych domen; nie ustawiaj pustej wartości bez potrzeby.
- Nie zmieniaj `APP_KEY` po starcie produkcji — zapisane klucze przestaną się odszyfrowywać,
  a adresy feedów (`OffersFeed::nameKey()`) się zmienią.
- Opublikowany `config/idosell.php` ze starszej wersji porównaj z nową (`idosell:install --force`).

## Panel aplikacji

```php
// routes/web.php
Route::middleware('idosell.panel')->prefix('panel')->name('app.')->group(function (): void {
    Route::get('/', [PanelController::class, 'index'])->name('panel');      // IDOSELL_LAUNCH_ROUTE=app.panel
    Route::post('/zapisz', [PanelController::class, 'save'])->name('save');
});

// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['panel', 'panel/*']);
    $middleware->trustProxies(at: '*');   // tylko za proxy / load balancerem z TLS
})
```

```php
public function index(IdosellLicense $license): View   // licencję wstrzykuje idosell.panel
{
    return view('panel', ['shops' => $license->shops()]);
}
```

```blade
<a href="{{ idosell_route('app.panel', ['tab' => 'raport']) }}">Raport</a>
<form method="POST" action="{{ idosell_route('app.save') }}">…</form>
```

**Rób:**
- Wszystkie strony panelu w grupie `idosell.panel` (wpuszcza z ważnym podpisanym linkiem i aktywną licencją, inaczej 403).
- Każdy link i formularz przez `idosell_route()` (w PHP: `Idosell::panelUrl()`); formularze **POST**.
- Licencję bierz z wstrzyknięcia `IdosellLicense $license` albo `Idosell::currentLicense()`.
- Osadzanie: `Content-Security-Policy: frame-ancestors 'self' https://*.idosell.com https://*.iai-shop.com`.

**Nie rób:**
- `route()` / `url()` w panelu (link bez podpisu → 403), formularzy GET (gubią podpis).
- `idosell.panel` na całej grupie `web` (strony spoza panelu IdoSell dostaną 403).
- `X-Frame-Options` (blokuje iframe), sesji/cookies jako identyfikacji sprzedawcy (w iframe bywają blokowane).
- `<meta name="referrer">` ani własnego nagłówka `Referrer-Policy` — SDK ustawia `no-referrer`
  (link zawiera podpis; meta ma pierwszeństwo przed nagłówkiem).
- Wydłużania ważności linków ponad potrzebę (`IDOSELL_LAUNCH_TTL`, domyślnie 30 min) ani
  jednorazowych tokenów (psują odświeżanie i nawigację w iframe).
- `IDOSELL_LAUNCH_URL` dla stron, które muszą wiedzieć, kto je otworzył (link bez podpisu).

## Logika aplikacji — listenery

Klasa w `app/Listeners` z `handle(Zdarzenie $event)` rejestruje się sama — nie dodawaj jej drugi raz
przez `Event::listen()` (wykona się dwukrotnie).

```php
namespace App\Listeners;

use Idosell\LaravelAppSdk\Events\LicenseActivated;
use Idosell\LaravelAppSdk\Facades\Idosell;

class InstallForMerchant
{
    public function handle(LicenseActivated $event): void
    {
        $snippets = Idosell::snippets($event->license);

        foreach ($event->license->shops() as $shop) {
            $campaignId = $snippets->ensureCampaign('Moja aplikacja', $shop['id']);
            $snippets->upsert($campaignId, 'Mój skrypt', '<script src="https://example.com/app.js" async></script>');
        }
    }
}
```

```php
namespace App\Listeners;

use Idosell\LaravelAppSdk\Events\LicenseDeactivating;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Throwable;

class CleanUpForMerchant   // bez ShouldQueue
{
    public function handle(LicenseDeactivating $event): void
    {
        try {
            $snippets = Idosell::snippets($event->license);

            foreach ($event->license->shops() as $shop) {
                if ($id = $snippets->findCampaign('Moja aplikacja', $shop['id'])) {
                    $snippets->deleteCampaign($id);
                }
            }
        } catch (Throwable $e) {
            report($e);   // nie przepuszczaj wyjątku
        }
    }
}
```

| Zdarzenie | Kiedy | Reguły |
|-----------|-------|--------|
| `LicenseActivated` (`license`, `payload`, `isNew`) | po zapisie i (online) udanym `installation/done`; też przy reinstalacji i modyfikacji licencji (`isNew === false`) | Idempotentnie (`ensureCampaign`/`upsert`/`ensure`). Wyjątek → webhook `error`, choć licencja już aktywna — łap i `report($e)`. Ciężka praca do kolejki. |
| `LicenseDeactivating` (`license`) | przy odinstalowaniu, **przed** wyłączeniem licencji | Ostatni moment z działającym kluczem. **Synchronicznie**, **bez wyjątków** (wyjątek przerywa deaktywację). |
| `LicenseDeactivated` (`license`, `deleted`) | po wyłączeniu (`mark_inactive`, domyślnie) albo usunięciu (`IDOSELL_ON_DEACTIVATION=delete`) | Porządki we własnej bazie; klucz Admin API może już nie działać. |
| `AppLaunched` (`payload`, `redirect`) | sprzedawca otworzył aplikację | Do statystyk. `payload` zawiera `api_license` i `sign`, `redirect` — podpisany link. Niczego z tego nie loguj. |

- `LicenseActivated::$payload` zawiera `api_license`, zaszyfrowany `api_key` i `contact_data` — nie loguj.
- Zamiast natychmiastowego usuwania snippetu możesz dać karencję:
  `$snippets->setEndDate($snippetId, now()->addDays(90)->format('Y-m-d'))` (IdoSell wyłączy go sam;
  `null` anuluje przy reinstalacji).
- OAuth (`authorization_type = OAuth`): token pobierz sam (`authorize/accessToken`) i zapisz
  `$event->license->update(['api_key' => $token])`; odświeżanie po Twojej stronie.

## Admin API i Apps API

```php
$api = $license->adminApi();                                  // albo adminApi(timeout: 5, retries: 0)
$api->get($api->admin('system/shopsData'));                   // → /api/admin/v8/system/shopsData
$api->post($api->admin('products/products/search'), ['params' => [/* … */]]);
$api->get($api->partners('offers/feed'), ['shop' => 1]);      // → /api/partners/v2/offers/feed

Idosell::apps()->setPrice($license->api_license, 250);        // NADPISUJE kwotę okresu
```

- `adminApi()` rzuca `IdosellException` dla licencji online bez potwierdzonego `installation/done`.
- Tylko ścieżki względne przez `admin()` / `partners()`; pełny URL → `ConfigurationException`.
- Przekierowania nie są śledzone (3xx → `ApiException`); `api_url` spoza polityki →
  `DisallowedApiUrlException` (`reason`: `scheme|not_public|not_allowed|invalid`, `host`).
- Nie wyłączaj weryfikacji TLS w kliencie HTTP. Szczegóły Admin API → skill `idosell-admin-api`.

## Zasady, które łatwo przeoczyć

1. **Nie omijaj middleware SDK** — własne trasy webhooków tylko z `['api', 'idosell.log-webhook',
   'idosell.verify-sign']`, kontrolerem SDK i nazwami `idosell.webhooks.*`.
2. **Nie loguj payloadów, `sign`, podpisanych linków ani `getMessage()` wyjątków bazodanowych**
   (SQL z wartościami = sekrety). Strukturę loguj przez `Redactor::redact()`; wyjątki — klasą i kodem.
3. **Nie filtruj webhooków po IP** — IP serwerów IdoSell nie są stałe.
4. **Nie hardkoduj domen sprzedawców** — host Admin API wynika z `api_url` licencji.
5. **Do wyszukiwania instalacji** używaj `Idosell::license($clientId)` /
   `IdosellLicense::query()->active()`; nie szukaj po `api_license` (kolumna szyfrowana).
6. **Produkcja**: `APP_DEBUG=false`, `LOG_LEVEL=info` (na `debug` logowany jest każdy webhook,
   zamaskowany), logi serwera WWW bez query stringu i `Referer` (podpisane linki), baza poza
   katalogiem wydania (sqlite w `database/` znika przy deployu), `php artisan migrate` po aktualizacji SDK.

## Testowanie

```php
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;

uses(InteractsWithIdosell::class);

it('instaluje aplikację u sprzedawcy', function () {
    $this->withIdosellConfig();
    $this->fakeIdosellApps();                    // keyset, installation/done, application/license (klient 555001)

    $this->postIdosellNewLicense()->assertJson(['status' => 'ok']);

    expect(Idosell::license(555001)?->active)->toBeTrue();
});
```

- `postIdosellLaunch()` / `postIdosellRemoveLicense()` wysyłają `api_license`
  `LIC-TEST-0000000000000000` — licencja z fabryki musi mieć ten sam klucz.
- Panel: `$this->get(Idosell::panelUrl('app.panel', [], $license))->assertOk()`.

Lokalnie bez panelu IdoSell: `php artisan idosell:simulate new-license|launch|remove-license`
(prawdziwy podpis i zaszyfrowany klucz; domyślnie `--client=990001`,
`--api-url=https://demo-shop.idosell.com/api/admin/`).

- Wysyła tylko pod host z `APP_URL` albo adres lokalny, bez przekierowań; nie działa na produkcji.
- Na czas testu: `IDOSELL_APP_TYPE=downloadable` i `IDOSELL_VERIFY_LICENSE_OWNER=false` (testowa
  licencja nie istnieje w Apps API) — **nigdy na produkcji**; po teście przywróć.
- Nie używaj `--client` prawdziwej instalacji — nadpiszesz jej dane.
- Test end-to-end: panel demo IdoSell + *Your data → Test panel ID or domain* w panelu dewelopera.
