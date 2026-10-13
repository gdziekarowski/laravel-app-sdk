# Referencja

## Zmienne `.env`

### Wymagane

| Zmienna | Znaczenie |
|---------|-----------|
| `IDOSELL_APPLICATION_ID` | ID aplikacji z panelu dewelopera |
| `IDOSELL_DEVELOPER` | login dewelopera |
| `IDOSELL_APPLICATION_KEY` | klucz aplikacji (sekret) |
| `IDOSELL_LAUNCH_ROUTE` | nazwa trasy panelu aplikacji, np. `app.panel` |

### Często używane

| Zmienna | Domyślnie | Znaczenie |
|---------|-----------|-----------|
| `IDOSELL_APP_TYPE` | `online` | `online` (aplikacja w iframe, potwierdzana w IdoSell) albo `downloadable` |
| `IDOSELL_ADMIN_API_ALLOWED_HOSTS` | `*.idosell.com,*.iai-shop.com` | domeny paneli, do których SDK wyśle klucz sprzedawcy (wzorce po przecinku). Pusta wartość wyłącza sprawdzanie listy (niezalecane). |
| `IDOSELL_VERIFY_LICENSE_OWNER` | `true` | sprawdzanie w IdoSell, czy licencja należy do sprzedawcy. `false` tylko do testów lokalnych z symulatorem — nigdy na produkcji. |
| `IDOSELL_LAUNCH_TTL` | `30` | ważność linków panelu (minuty) |
| `IDOSELL_ON_DEACTIVATION` | `mark_inactive` | po odinstalowaniu: `mark_inactive` (licencja zostaje) albo `delete` |
| `IDOSELL_LOG_CHANNEL` | domyślny kanał | kanał logów SDK |

### Rzadko zmieniane

| Zmienna | Domyślnie | Znaczenie |
|---------|-----------|-----------|
| `IDOSELL_ADMIN_API_VERSION` | `v8` | wersja Admin API |
| `IDOSELL_PARTNERS_API_VERSION` | `v2` | wersja API `partners` (feed) |
| `IDOSELL_ADMIN_API_TIMEOUT` / `_CONNECT_TIMEOUT` | `15` / `5` | timeouty Admin API (s) |
| `IDOSELL_ADMIN_API_RETRIES` / `_RETRY_DELAY` | `3` / `300` | ponowienia i odstęp (ms) |
| `IDOSELL_APPS_BASE_URL` | `https://apps.idosell.com/api` | adres Apps API |
| `IDOSELL_APPS_KEYSET_URL` | `https://apps.idosell.com/keyset` | adres wektora do odszyfrowania kluczy |
| `IDOSELL_APPS_TIMEOUT` / `_CONNECT_TIMEOUT` | `10` / `5` | timeouty Apps API (s) |
| `IDOSELL_APPS_RETRIES` / `_RETRY_DELAY` | `3` / `200` | ponowienia i odstęp (ms) |
| `IDOSELL_SIGN_DATE_TOLERANCE` | `1` | tolerancja czasu podpisu webhooków — nie zmieniaj |
| `IDOSELL_DB_CONNECTION` | domyślne | połączenie bazy dla licencji |
| `IDOSELL_LICENSES_TABLE` | `idosell_licenses` | nazwa tabeli licencji |
| `IDOSELL_RUN_MIGRATIONS` | `true` | `false`, gdy opublikowałeś migracje do aplikacji |
| `IDOSELL_ROUTES_ENABLED` | `true` | `false`, gdy rejestrujesz trasy webhooków sam |
| `IDOSELL_ROUTES_PREFIX` | `api/idosell/webhooks` | prefiks adresów webhooków |
| `IDOSELL_LAUNCH_URL` | — | stały adres zamiast trasy (zob. niżej) |
| `IDOSELL_LAUNCH_SIGNED` | `true` | podpisane linki panelu — `idosell.panel` wymaga `true` |
| `IDOSELL_LOG_WEBHOOKS` | `true` | log `debug` każdego webhooka (sekrety maskowane) |

## Komendy

| Komenda | Działanie |
|---------|-----------|
| `idosell:install [--migrations] [--skills] [--force]` | publikuje config, wypisuje brakujące zmienne i adresy webhooków. `--migrations` publikuje migracje (ustaw wtedy `IDOSELL_RUN_MIGRATIONS=false`), `--skills` — bazę wiedzy o IdoSell dla asystentów AI. |
| `idosell:doctor [--live]` | sprawdza konfigurację, podpis, trasy i tabelę licencji; `--live` łączy się z apps.idosell.com |
| `idosell:licenses [--active] [--client=]` | lista zapisanych instalacji (bez sekretów) |
| `idosell:simulate {new-license\|launch\|remove-license}` | wysyła do aplikacji webhook jak IdoSell — tylko poza produkcją |

### `idosell:simulate`

| Opcja | Domyślnie |
|-------|-----------|
| `--client` | `990001` |
| `--application` | `IDOSELL_APPLICATION_ID` |
| `--api-url` | `https://clientxxxx.idosell.com/api/admin/` |
| `--api-key` | `admin-api-key-0000000000000000` |
| `--api-license` | `LIC-SIMULATED-0000000000` |
| `--url` | `{APP_URL}/{IDOSELL_ROUTES_PREFIX}/{webhook}` |

- `--url` musi wskazywać Twoją aplikację (host z `APP_URL`) albo adres lokalny (`localhost`, `127.0.0.1`,
  `*.localhost`, `*.test`) — symulator wysyła prawdziwy podpis.
- `launch` i `remove-license` wymagają tego samego `--api-license`, z którym wykonano `new-license`.
- Nie używaj `--client` prawdziwej instalacji — nadpiszesz jej dane.
- Wymagane ustawienia `.env`: [01-pierwsza-aplikacja.md, krok 7](01-pierwsza-aplikacja.md#7-test-lokalny-bez-panelu-idosell).

## Zaawansowane

### Własne trasy webhooków

Zmiana prefiksu: `IDOSELL_ROUTES_PREFIX=api/webhooks/idosell`. Pełna kontrola: `IDOSELL_ROUTES_ENABLED=false`
i własne trasy z kontrolerem SDK (zachowaj nazwy `idosell.webhooks.*`):

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

`idosell:simulate` wywołuj wtedy z `--url`.

### Własny adres po uruchomieniu

Domyślnie SDK zwraca podpisany link do `IDOSELL_LAUNCH_ROUTE` — tak powinno zostać. Alternatywy:

- `IDOSELL_LAUNCH_URL=https://…` — stały adres **bez podpisu**, więc bez identyfikacji sprzedawcy
  i bez `idosell.panel`. Tylko dla stron, które nie potrzebują wiedzieć, kto je otworzył.
- Własna logika (np. w `AppServiceProvider::boot()`); link musi identyfikować sprzedawcę w bezpieczny sposób:

```php
Idosell::resolveLaunchUrlUsing(
    fn (array $payload): string => URL::temporarySignedRoute('app.welcome', now()->addMinutes(5), [
        'client' => $payload['client_id'],
        'application' => $payload['application_id'],
    ]),
);
```

### Publikacja migracji

```bash
php artisan idosell:install --migrations
```

Następnie `IDOSELL_RUN_MIGRATIONS=false`, żeby migracje nie uruchamiały się dwa razy.

### `APP_KEY`

Klucze sprzedawców w bazie są szyfrowane `APP_KEY`, a adresy feedów (`OffersFeed::nameKey()`) są z niego
wyliczane. Zmiana `APP_KEY` unieważnia zapisane klucze i zmienia adresy feedów.
