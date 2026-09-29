# Konfiguracja

Plik: `config/idosell.php` (publikacja: `php artisan idosell:install`).

## Zmienne `.env`

### Aplikacja

| Zmienna | Domyślnie | Znaczenie |
|---------|-----------|-----------|
| `IDOSELL_APPLICATION_ID` | — | ID aplikacji z panelu dewelopera |
| `IDOSELL_DEVELOPER` | — | login dewelopera |
| `IDOSELL_APPLICATION_KEY` | — | klucz aplikacji (sekret) |
| `IDOSELL_APP_TYPE` | `online` | `online` albo `downloadable` |
| `IDOSELL_SIGN_DATE_TOLERANCE` | `1` | tolerancja daty podpisu `sign` (dni) |
| `IDOSELL_APPS_BASE_URL` | `https://apps.idosell.com/api` | adres Apps API |
| `IDOSELL_APPS_KEYSET_URL` | `https://apps.idosell.com/keyset` | adres IV do deszyfracji `api_key` |
| `IDOSELL_APPS_TIMEOUT` / `_CONNECT_TIMEOUT` | `10` / `5` | timeouty Apps API (s) |
| `IDOSELL_APPS_RETRIES` / `_RETRY_DELAY` | `3` / `200` | próby i odstęp (ms) |

### Admin API

| Zmienna | Domyślnie | Znaczenie |
|---------|-----------|-----------|
| `IDOSELL_ADMIN_API_VERSION` | `v8` | wersja bramki `admin` |
| `IDOSELL_PARTNERS_API_VERSION` | `v2` | wersja bramki `partners` |
| `IDOSELL_ADMIN_API_TIMEOUT` / `_CONNECT_TIMEOUT` | `15` / `5` | timeouty (s) |
| `IDOSELL_ADMIN_API_RETRIES` / `_RETRY_DELAY` | `3` / `300` | próby i odstęp (ms) |

### Licencje

| Zmienna | Domyślnie | Znaczenie |
|---------|-----------|-----------|
| `IDOSELL_DB_CONNECTION` | domyślne połączenie | połączenie bazy dla licencji |
| `IDOSELL_LICENSES_TABLE` | `idosell_licenses` | nazwa tabeli |
| `IDOSELL_RUN_MIGRATIONS` | `true` | `false`, gdy migracje są opublikowane do aplikacji |
| `IDOSELL_ON_DEACTIVATION` | `mark_inactive` | `mark_inactive` (licencja zostaje z `active = false`) albo `delete` |

### Trasy webhooków

| Zmienna | Domyślnie | Znaczenie |
|---------|-----------|-----------|
| `IDOSELL_ROUTES_ENABLED` | `true` | `false`, gdy rejestrujesz trasy sam |
| `IDOSELL_ROUTES_PREFIX` | `api/idosell/webhooks` | prefiks adresów webhooków |

### Uruchomienie z panelu (launch)

| Zmienna | Domyślnie | Znaczenie |
|---------|-----------|-----------|
| `IDOSELL_LAUNCH_ROUTE` | — | nazwa trasy panelu aplikacji |
| `IDOSELL_LAUNCH_URL` | — | stały adres zamiast trasy |
| `IDOSELL_LAUNCH_SIGNED` | `true` | podpisany URL; `idosell.panel` wymaga `true` |
| `IDOSELL_LAUNCH_TTL` | `30` | ważność podpisanych linków panelu (min) |

### Logi

| Zmienna | Domyślnie | Znaczenie |
|---------|-----------|-----------|
| `IDOSELL_LOG_CHANNEL` | domyślny kanał | kanał logów pakietu |
| `IDOSELL_LOG_WEBHOOKS` | `true` | log `debug` każdego webhooka (sekrety maskowane) |

Maskowane klucze: `logging.redact` w `config/idosell.php`.

## Typ aplikacji

- `online`: aktywacja potwierdzana w IdoSell (`installation/done`), payload zawiera `api_key`.
- `downloadable`: bez potwierdzenia, bez `api_key` i `authorization_type` w payloadzie.

## Własne trasy webhooków

Zmiana samego prefiksu: `IDOSELL_ROUTES_PREFIX=api/webhooks/idosell`.

Pełna kontrola: `IDOSELL_ROUTES_ENABLED=false` i trasy na kontrolerze pakietu. Nazwy zachowaj z
prefiksem `idosell.webhooks.` (korzystają z nich `idosell:doctor` i pomocniki testowe), a
`idosell:simulate` wywołuj z `--url`:

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

## Migracje

Domyślnie ładowane z pakietu. Publikacja do `database/migrations`:

```bash
php artisan idosell:install --migrations
```

i `IDOSELL_RUN_MIGRATIONS=false`.

## `APP_KEY`

`api_key` i `api_license` są szyfrowane `APP_KEY`, a `OffersFeed::nameKey()` jest z niego wyliczany.
Zmiana `APP_KEY` bez przeszyfrowania tabeli licencji unieważnia zapisane klucze i zmienia publiczne
adresy feedów.
