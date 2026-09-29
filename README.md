# IdoSell App SDK dla Laravela

Pakiet `idosell/laravel-app-sdk` do budowy aplikacji [IdoSell Apps](https://apps.idosell.com): webhooki
licencji, panel aplikacji w iframe, Admin API sprzedawcy, Apps API.

Wymagania: PHP 8.2+ (Laravel 13: PHP 8.3+), Laravel 11, 12 lub 13, `ext-json`, `ext-openssl`.

## Instalacja

```bash
composer require idosell/laravel-app-sdk
php artisan idosell:install
php artisan migrate
```

`idosell:install` publikuje `config/idosell.php` i wypisuje brakujące zmienne `.env` oraz adresy webhooków.

## Konfiguracja minimalna

```dotenv
APP_URL=https://twoja-aplikacja.example.com
IDOSELL_APPLICATION_ID=1234
IDOSELL_DEVELOPER=twoj-login-dewelopera
IDOSELL_APPLICATION_KEY=klucz-aplikacji-z-panelu
IDOSELL_LAUNCH_ROUTE=app.panel
```

Adresy do wpisania w panelu dewelopera:

| Pole | Adres |
|------|-------|
| `url_webhook_new_license` | `{APP_URL}/api/idosell/webhooks/new-license` |
| `url_webhook_remove_license` | `{APP_URL}/api/idosell/webhooks/remove-license` |
| URL uruchomienia aplikacji | `{APP_URL}/api/idosell/webhooks/launch` |

Sprawdzenie konfiguracji: `php artisan idosell:doctor --live`. Wszystkie zmienne:
[docs/01-konfiguracja.md](docs/01-konfiguracja.md).

## Szybki start

**1. Panel aplikacji.** Trasa z `IDOSELL_LAUNCH_ROUTE` i kolejne trasy panelu w grupie `idosell.panel`:

```php
// routes/web.php
use App\Http\Controllers\PanelController;

Route::middleware('idosell.panel')->prefix('panel')->name('app.')->group(function (): void {
    Route::get('/', [PanelController::class, 'index'])->name('panel');
    Route::post('/sklepy', [PanelController::class, 'fetchShops'])->name('shops.fetch');
});
```

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['panel', 'panel/*']);
})
```

```php
// app/Http/Controllers/PanelController.php
namespace App\Http\Controllers;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Contracts\View\View;

class PanelController extends Controller
{
    public function index(IdosellLicense $license): View
    {
        return view('panel', ['shops' => $license->shops()]);
    }

    public function fetchShops(IdosellLicense $license): View
    {
        $api = $license->adminApi();

        return view('panel', ['shops' => $api->get($api->admin('system/shopsData'))['shop_contact'] ?? []]);
    }
}
```

```blade
{{-- resources/views/panel.blade.php --}}
<form method="POST" action="{{ idosell_route('app.shops.fetch') }}">
    <button type="submit">Pokaż sklepy</button>
</form>
```

**2. Instalacja i odinstalowanie u sprzedawcy.** Listener w `app/Listeners` (Laravel rejestruje go automatycznie):

```php
namespace App\Listeners;

use Idosell\LaravelAppSdk\Events\LicenseActivated;

class InstallForMerchant
{
    public function handle(LicenseActivated $event): void
    {
        foreach ($event->license->shops() as $shop) {
            // konfiguracja sklepu $shop['id']; musi znieść ponowne wywołanie
        }
    }
}
```

Sprzątanie po odinstalowaniu: [docs/03-zdarzenia.md](docs/03-zdarzenia.md).

**3. Test lokalny** (aplikacja musi działać pod `APP_URL`):

```bash
php artisan idosell:simulate new-license --client=990001
php artisan idosell:simulate launch --client=990001      # w odpowiedzi redirect: podpisany link do panelu
```

Ograniczenia `simulate`: [docs/06-komendy.md](docs/06-komendy.md).

## Dokumentacja

1. [Konfiguracja](docs/01-konfiguracja.md): zmienne `.env`, typ aplikacji, trasy webhooków, baza, logi
2. [Panel aplikacji](docs/02-panel.md): `idosell.panel`, licencja w kontrolerze, linki, formularze, CSRF
3. [Zdarzenia](docs/03-zdarzenia.md): instalacja, odinstalowanie, uruchomienie, OAuth
4. [API](docs/04-api.md): model licencji, Admin API, snippety, feed, Apps API, fasada
5. [Testowanie](docs/05-testowanie.md): trait `InteractsWithIdosell`, fabryka
6. [Komendy](docs/06-komendy.md): `install`, `doctor`, `simulate`, `licenses`
7. [Migracja](docs/07-migracja.md): przejście z własnej implementacji

Zmiany: [CHANGELOG.md](CHANGELOG.md). Licencja MIT.
