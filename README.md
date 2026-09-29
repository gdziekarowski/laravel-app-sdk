# IdoSell App SDK dla Laravela

Pakiet `idosell/laravel-app-sdk` do budowy aplikacji [IdoSell Apps](https://apps.idosell.com). Obsługuje
webhooki licencji (instalacja, odinstalowanie, uruchomienie), chroni panel aplikacji w iframe i daje
klientów Apps API oraz Admin API sprzedawcy. Wymaga PHP 8.2+ (Laravel 13: 8.3+) i Laravela 11, 12
lub 13.

## Instalacja

```bash
composer require idosell/laravel-app-sdk
php artisan idosell:install      # config/idosell.php, checklista .env, adresy webhooków
php artisan migrate              # tabela idosell_licenses
```

## Konfiguracja

```dotenv
IDOSELL_APPLICATION_ID=1234
IDOSELL_DEVELOPER=twoj-login-dewelopera
IDOSELL_APPLICATION_KEY=klucz-aplikacji-z-panelu   # sekret: .env / Key Vault, nigdy repozytorium
IDOSELL_LAUNCH_ROUTE=app.panel                     # trasa, na którą trafia sprzedawca po „Uruchom”
IDOSELL_LAUNCH_TTL=30                              # ważność podpisanych linków panelu (min)
```

Adresy do wpisania w panelu dewelopera (trasy rejestruje pakiet):

| Pole w panelu dewelopera | Adres |
|--------------------------|-------|
| `url_webhook_new_license` | `{APP_URL}/api/idosell/webhooks/new-license` |
| `url_webhook_remove_license` | `{APP_URL}/api/idosell/webhooks/remove-license` |
| URL uruchomienia aplikacji | `{APP_URL}/api/idosell/webhooks/launch` |

Sprawdzenie: `php artisan idosell:doctor --live`. Pozostałe opcje: [`config/idosell.php`](config/idosell.php).

## Middleware

| Alias | Klasa | Do czego |
|-------|-------|----------|
| `idosell.panel` | `Http\Middleware\EnsureIdosellLicense` | Panel aplikacji: podpisany URL + aktywna licencja, inaczej 403 |
| `idosell.verify-sign` | `Http\Middleware\VerifyIdosellSign` | Webhooki: weryfikacja `sign` |
| `idosell.log-webhook` | `Http\Middleware\LogIdosellWebhook` | Webhooki: log `debug` z maskowaniem sekretów |

## Panel aplikacji

Panel sklepu otwiera aplikację w iframe z innej domeny, więc cookies sesji bywają blokowane. Kontekst
sprzedawcy (`client`, `application`) niesie podpisany URL czasowy.

```php
// routes/web.php
Route::middleware('idosell.panel')->group(function (): void {
    Route::get('/panel', PanelController::class)->name('app.panel');
    Route::post('/panel/shops/fetch', FetchShopsController::class)->name('shops.fetch');
});
```

```php
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;

$license = Idosell::currentLicense();                    // w kontrolerze trasy z idosell.panel

public function __invoke(IdosellLicense $license) {}     // albo wstrzyknięcie
```

Każdy link i formularz w panelu musi nieść kontekst:

```blade
<a href="{{ idosell_route('app.panel', ['tab' => 'ustawienia']) }}">Ustawienia</a>

<form method="POST" action="{{ idosell_route('shops.fetch') }}">
    <button type="submit">Pobierz sklepy</button>
</form>
```

CSRF: POST z iframe bez cookies dostanie 419. Wyłącz weryfikację tokenu dla tras panelu, bo tę rolę
pełni podpisany URL czasowy:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['panel/*']);
})
```

`$middleware->appendToGroup('web', 'idosell.panel')` obejmie całą grupę `web`, także strony bez
kontekstu sprzedawcy, i te dostaną 403. Szczegóły: [docs/07-panel.md](docs/07-panel.md).

## Zdarzenia

| Zdarzenie | Kiedy | Właściwości |
|-----------|-------|-------------|
| `LicenseActivated` | licencja zapisana, instalacja potwierdzona | `license`, `payload`, `isNew` |
| `LicenseDeactivating` | przed wyłączeniem, klucz Admin API jeszcze działa | `license` |
| `LicenseDeactivated` | licencja wyłączona albo usunięta | `license`, `deleted` |
| `AppLaunched` | zwrócono adres przekierowania do panelu | `payload`, `redirect` |

```php
use Idosell\LaravelAppSdk\Events\LicenseActivated;
use Idosell\LaravelAppSdk\Events\LicenseDeactivating;

Event::listen(LicenseActivated::class, function (LicenseActivated $event): void {
    // webhook bywa dostarczany ponownie (isNew = false), logika musi być idempotentna
    foreach ($event->license->shops() as $shop) {
        ConfigureShop::dispatch($event->license, $shop['id']);
    }
});

Event::listen(LicenseDeactivating::class, function (LicenseDeactivating $event): void {
    Idosell::snippets($event->license)->delete($snippetId);   // synchronicznie, póki klucz działa
});
```

## Metody

### Fasada `Idosell`

| Metoda | Zwraca |
|--------|--------|
| `license(int $clientId, ?int $applicationId = null)` | `?IdosellLicense` (aplikacja domyślnie z configu) |
| `currentLicense()` | `?IdosellLicense` licencja bieżącego żądania panelu |
| `panelUrl(string $route, array $parameters = [], ?IdosellLicense $license = null)` | podpisany URL z kontekstem |
| `adminApi(IdosellLicense $license, ?int $timeout = null, ?int $retries = null)` | `AdminApiClient` |
| `snippets(IdosellLicense $license)` | `Resources\Snippets` |
| `offersFeed(IdosellLicense $license)` | `Resources\OffersFeed` |
| `apps()` | `AppsApiClient` |
| `sign(?string $date = null)` / `verify(string $sign)` | podpis `sign` / `bool` |
| `decryptApiKey(string $encrypted)` | odszyfrowany `api_key` |
| `resolveLaunchUrlUsing(?Closure $callback)` | własny adres przekierowania po launch |

Helper: `idosell_route(string $name, array $parameters = [], ?IdosellLicense $license = null)`, czyli `panelUrl()`.

### Model `IdosellLicense`

```php
$license->client_id; $license->application_id; $license->active; $license->api_url;
$license->shops();        // [['id' => 1, 'name' => 'Sklep 1'], ...]
$license->domain();       // host panelu sprzedawcy
$license->adminApi();     // AdminApiClient

IdosellLicense::query()->active()->forClient($clientId)->forApplication()->first();
```

### Admin API sprzedawcy

```php
$api = $license->adminApi();

$api->get($api->admin('products/products'), ['resultsLimit' => 100]);   // /api/admin/v8/...
$api->post($api->admin('products/products/search'), [...]);
$api->put($api->partners('offers/feed'), [...]);                        // /api/partners/v2/...
$api->delete($api->admin('...'), [...]);
```

Host z `api_url` licencji, autoryzacja z `authorization_type` (`X-API-KEY` albo Bearer). Błąd HTTP:
`Exceptions\ApiException`. Szczegóły: [docs/03-admin-api.md](docs/03-admin-api.md).

### Snippety

```php
$snippets = Idosell::snippets($license);

$campaignId = $snippets->ensureCampaign('Moja aplikacja', shopId: 1);
$snippetId = $snippets->upsert($campaignId, 'Mój skrypt', '<script src="https://example.com/app.js" async></script>', [
    'zone' => 'head',            // head | bodyBegin | bodyEnd
]);
$snippets->setEndDate($snippetId, '2026-12-31');
$snippets->delete($snippetId);
$snippets->deleteCampaign($campaignId);
```

`ensureCampaign()` i `upsert()` szukają po nazwie, więc ponowne wywołanie nie tworzy duplikatów.

### Feed ofertowy

```php
use Idosell\LaravelAppSdk\Resources\OffersFeed;

$feeds = Idosell::offersFeed($license);

$params = [
    'shop' => 1,
    'name' => 'Feed mojej aplikacji',
    'nameKey' => OffersFeed::nameKey(1),
    'format' => 'IOF30',
    // pozostałe pola: docs/04-snippety-i-feed.md
];

['id' => $feedId, 'url' => $url] = $feeds->ensure($params);   // tworzy albo aktualizuje
$feeds->disable($feedId, $params);
$feeds->delete($feedId);
```

### Apps API

```php
$apps = Idosell::apps();

$apps->licenses(active: true);                 // application/license
$apps->setPrice($license->api_license, 250);   // application/license/setPrice
$apps->post('/application/...', [...]);        // dowolny endpoint, `sign` dokładany automatycznie
```

`installation/done` pakiet wywołuje sam przy aktywacji aplikacji typu `online`.

## Własne trasy webhooków

Przy `IDOSELL_ROUTES_ENABLED=false` zachowaj prefiks nazw `idosell.webhooks.`:

```php
use Idosell\LaravelAppSdk\Http\Controllers\WebhookController;

Route::middleware(['api', 'idosell.log-webhook', 'idosell.verify-sign'])
    ->prefix('moje/webhooki')
    ->name('idosell.webhooks.')
    ->group(function (): void {
        Route::post('new-license', [WebhookController::class, 'newLicense'])->name('new-license');
        Route::post('remove-license', [WebhookController::class, 'removeLicense'])->name('remove-license');
        Route::post('launch', [WebhookController::class, 'launch'])->name('launch');
    });
```

## Testy w aplikacji

```php
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;

uses(InteractsWithIdosell::class);

it('instaluje aplikację', function () {
    $this->withIdosellConfig();
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense(['client_id' => 555001])->assertJson(['status' => 'ok']);
});

it('wpuszcza do panelu', function () {
    $license = IdosellLicense::factory()->create();

    $this->get(Idosell::panelUrl('app.panel', [], $license))->assertOk();
});
```

Pomocniki: `postIdosellNewLicense()`, `postIdosellRemoveLicense()`, `postIdosellLaunch()`, `idosellSign()`,
`idosellEncryptedApiKey()`. Fabryka: `IdosellLicense::factory()->inactive()|oauth()|withShops([...])`.

## Komendy

| Komenda | Opis |
|---------|------|
| `idosell:install [--migrations] [--skills] [--force]` | Publikacja configu, checklista `.env`, adresy webhooków |
| `idosell:doctor [--live]` | Diagnostyka konfiguracji; `--live` łączy się z Apps API |
| `idosell:simulate <new-license\|remove-license\|launch> [--client=] [--url=]` | Podpisany webhook na własną aplikację (poza produkcją) |
| `idosell:licenses [--active] [--client=]` | Lista instalacji bez sekretów |

## Więcej

[Quickstart](docs/01-quickstart.md) ·
[Cykl życia](docs/02-cykl-zycia.md) ·
[Admin API](docs/03-admin-api.md) ·
[Snippety i feed](docs/04-snippety-i-feed.md) ·
[Testowanie](docs/05-testowanie.md) ·
[Migracja](docs/06-migracja.md) ·
[Panel w iframe](docs/07-panel.md) ·
[CHANGELOG](CHANGELOG.md)

Licencja MIT.
