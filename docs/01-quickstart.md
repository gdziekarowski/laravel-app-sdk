# Quickstart — od zera do działającej aplikacji

Kroki od konta dewelopera do pierwszej instalacji aplikacji u sprzedawcy.

## 0. Zanim zaczniesz

Załóż konto na https://apps.idosell.com i utwórz aplikację. Zanotuj:

- **application_id** — identyfikator aplikacji,
- **login dewelopera** — składnik podpisu,
- **applicationKey** — sekret aplikacji,
- **typ aplikacji** — `online` (hostowana u Ciebie) albo `downloadable`.

Aplikacja typu online wymaga publicznie dostępnego HTTPS. Do pracy lokalnej możesz użyć tunelu
(np. `ngrok`, `expose`) albo pominąć panel i symulować webhooki komendą `idosell:simulate`.

## 1. Instalacja paczki

```bash
composer require idosell/laravel-app-sdk
php artisan idosell:install
php artisan migrate
```

Powstanie tabela `idosell_licenses` — po jednym rekordzie na instalację aplikacji u sprzedawcy.

## 2. Konfiguracja

```dotenv
APP_URL=https://twoja-aplikacja.example.com   # musi być poprawny — z niego biorą się adresy webhooków

IDOSELL_APPLICATION_ID=1234
IDOSELL_DEVELOPER=twoj-login
IDOSELL_APPLICATION_KEY=klucz-aplikacji
IDOSELL_LAUNCH_ROUTE=app.panel
```

> `APP_KEY` szyfruje `api_key` i `api_license` w bazie. Jego zmiana unieważnia zapisane sekrety
> wszystkich instalacji — sprzedawcy musieliby przeinstalować aplikację.

## 3. Trasa panelu aplikacji

Sprzedawca wchodzi do aplikacji z panelu sklepu, przez **podpisany URL czasowy**. Podpis jest jedyną
identyfikacją — trasa musi go weryfikować:

```php
// routes/web.php
Route::get('/panel', PanelAplikacji::class)
    ->middleware('idosell.panel')
    ->name('app.panel');
```

W kontrolerze/komponencie:

```php
$license = Idosell::currentLicense();   // aktywna licencja, ustawiona przez `idosell.panel`
```

Linki i formularze w panelu: [07-panel.md](07-panel.md).

## 4. Wpięcie własnej logiki

```php
// app/Providers/AppServiceProvider.php — boot()
Event::listen(LicenseActivated::class, InstallForMerchant::class);
Event::listen(LicenseDeactivating::class, CleanUpForMerchant::class);
```

Sprzątanie robi się w `LicenseDeactivating`, nie w `LicenseDeactivated` — patrz
[02-cykl-zycia.md](02-cykl-zycia.md).

## 5. Rejestracja adresów w panelu dewelopera

```bash
php artisan idosell:install     # wypisze komplet adresów
```

| Pole w panelu | Adres |
|---------------|-------|
| `url_webhook_new_license` | `{APP_URL}/api/idosell/webhooks/new-license` |
| `url_webhook_remove_license` | `{APP_URL}/api/idosell/webhooks/remove-license` |
| URL uruchomienia aplikacji | `{APP_URL}/api/idosell/webhooks/launch` |

## 6. Weryfikacja

```bash
php artisan idosell:doctor --live
```

Komenda sprawdza konfigurację, zarejestrowane trasy, tabelę licencji, dostępność `keyset`
i poprawność podpisu wobec Apps API.

Następnie przepuść pełny cykl lokalnie:

```bash
php artisan idosell:simulate new-license
php artisan idosell:licenses
php artisan idosell:simulate launch
php artisan idosell:simulate remove-license
```

## 7. Pierwsza prawdziwa instalacja

Włącz aplikację na koncie testowym sprzedawcy (Sandbox Demo). Jeżeli coś nie zadziała, ustaw
`LOG_LEVEL=debug` — middleware `idosell.log-webhook` zapisuje każde przychodzące żądanie **przed**
weryfikacją podpisu, więc zobaczysz także próby odrzucone.

## Dalej

- [02-cykl-zycia.md](02-cykl-zycia.md) — co dokładnie dzieje się przy instalacji i odinstalowaniu
