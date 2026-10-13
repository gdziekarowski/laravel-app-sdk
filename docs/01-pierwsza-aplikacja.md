# Pierwsza aplikacja krok po kroku

Po tym przewodniku będziesz mieć aplikację, którą sprzedawca instaluje w panelu sklepu IdoSell,
otwiera ją w panelu i widzi listę swoich sklepów pobraną z Admin API.

**Potrzebujesz:**

- konta dewelopera w [apps.idosell.com](https://apps.idosell.com) z dodaną aplikacją,
- aplikacji Laravel 11+ dostępnej publicznie przez **https** (webhooki wysyła serwer IdoSell, więc
  `localhost` wystarczy tylko do testu lokalnego z kroku 7),
- panelu demo IdoSell do testów — [zamówienie dostępu](https://www.idosell.com/pl/demo/zamowienie-dostepu-do-demo/).

Jak to wszystko działa pod spodem: [02-jak-to-dziala.md](02-jak-to-dziala.md).

---

## 1. Zainstaluj pakiet

```bash
composer require idosell/laravel-app-sdk
php artisan idosell:install
php artisan migrate
```

`idosell:install` publikuje `config/idosell.php` i wypisuje adresy webhooków. `migrate` tworzy tabelę
`idosell_licenses` — jeden wiersz na każdą instalację u sprzedawcy.

## 2. Uzupełnij `.env`

Wartości weźmiesz z panelu dewelopera: apps.idosell.com → **Applications** → edycja aplikacji.

```dotenv
APP_URL=https://twoja-aplikacja.example.com

IDOSELL_APPLICATION_ID=1234                 # nagłówek „Editing the application (ID: …)”
IDOSELL_DEVELOPER=twoj-login                # login do panelu dewelopera
IDOSELL_APPLICATION_KEY=klucz-aplikacji     # zakładka Technical data → „Application key” (SEKRET)
IDOSELL_LAUNCH_ROUTE=app.panel              # nazwa trasy panelu z kroku 3

LOG_LEVEL=debug                             # na czas uruchamiania — widać każdy webhook
```

`IDOSELL_APPLICATION_KEY` to sekret: nigdy nie trafia do repozytorium.

## 3. Dodaj trasy panelu

Panel aplikacji to zwykłe trasy Laravela w grupie z middleware `idosell.panel`. Middleware wpuszcza tylko
sprzedawcę, który przyszedł z panelu IdoSell, i podaje Ci jego licencję.

```php
// routes/web.php
use App\Http\Controllers\PanelController;

Route::middleware('idosell.panel')->prefix('panel')->name('app.')->group(function (): void {
    Route::get('/', [PanelController::class, 'index'])->name('panel');
    Route::post('/sklepy', [PanelController::class, 'shops'])->name('shops');
});
```

W iframe na innej domenie cookies (a z nimi token CSRF) zwykle nie docierają. Wyłącz CSRF
dla panelu — chroni go podpisany link. Jeśli aplikacja stoi za proxy lub load balancerem z TLS, dodaj też
`trustProxies`, inaczej podpis linku się nie zgodzi:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['panel', 'panel/*']);
    $middleware->trustProxies(at: '*');   // tylko za proxy / load balancerem
})
```

## 4. Kontroler i widok

```php
// app/Http/Controllers/PanelController.php
namespace App\Http\Controllers;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Contracts\View\View;

class PanelController extends Controller
{
    // Licencję sprzedawcy wstrzykuje middleware idosell.panel.
    public function index(IdosellLicense $license): View
    {
        return view('panel', ['shops' => null]);
    }

    public function shops(IdosellLicense $license): View
    {
        $api = $license->adminApi();
        $data = $api->get($api->admin('system/shopsData'));

        return view('panel', ['shops' => $data['shop_contact'] ?? []]);
    }
}
```

```blade
{{-- resources/views/panel.blade.php --}}
<form method="POST" action="{{ idosell_route('app.shops') }}">
    <button type="submit">Pokaż sklepy</button>
</form>

@foreach ($shops ?? [] as $shop)
    <p>{{ $shop['shop_name'] ?? '' }}</p>
@endforeach
```

Każdy link i formularz w panelu buduj przez `idosell_route()` — niesie podpis, bez którego kolejne
żądanie dostanie 403. Formularze wysyłaj metodą POST. Więcej: [03-panel.md](03-panel.md).

## 5. Twój kod przy instalacji i odinstalowaniu (opcjonalnie)

Jeśli przy instalacji coś ma się wydarzyć w sklepie (np. wstawienie skryptu), napisz listener. Klasa
w `app/Listeners` rejestruje się sama.

```php
// app/Listeners/InstallForMerchant.php
namespace App\Listeners;

use Idosell\LaravelAppSdk\Events\LicenseActivated;

class InstallForMerchant
{
    public function handle(LicenseActivated $event): void
    {
        // $event->license — licencja sprzedawcy; kod musi znieść ponowne wywołanie
    }
}
```

Sprzątanie po odinstalowaniu i przykłady ze snippetami: [04-zdarzenia.md](04-zdarzenia.md).

## 6. Sprawdź konfigurację

```bash
php artisan idosell:doctor --live
```

Komenda sprawdza `.env`, podpis, trasy webhooków, tabelę licencji i połączenie z apps.idosell.com.

## 7. Test lokalny (bez panelu IdoSell)

Symulator wysyła do Twojej aplikacji webhooki takie jak IdoSell. Testowa licencja nie istnieje
w IdoSell, więc **na czas testu** ustaw w `.env`:

```dotenv
APP_URL=http://localhost:8000
IDOSELL_APP_TYPE=downloadable
IDOSELL_VERIFY_LICENSE_OWNER=false
```

```bash
php artisan serve
# w drugim terminalu:
php artisan idosell:simulate new-license --client=990001   # instalacja → "Webhook obsłużony poprawnie"
php artisan idosell:simulate launch --client=990001        # w odpowiedzi "redirect" — otwórz go w przeglądarce
php artisan idosell:simulate remove-license --client=990001
```

Panel otworzy się z linku `redirect`. Przycisk „Pokaż sklepy” zwróci błąd — testowy sklep i klucz
nie istnieją w IdoSell, to normalne.

**Po teście przywróć** `APP_URL`, `IDOSELL_APP_TYPE=online` i usuń `IDOSELL_VERIFY_LICENSE_OWNER=false`.

## 8. Wdróż i ustaw adresy w panelu dewelopera

Wdróż aplikację pod publicznym adresem https. Na serwerze pozwól osadzać aplikację tylko w panelu
IdoSell — żadna inna strona nie wstawi jej wtedy w ramkę, żeby podstępem kazać sprzedawcy coś kliknąć:

```nginx
# nginx, blok server
add_header Content-Security-Policy "frame-ancestors 'self' https://*.idosell.com https://*.iai-shop.com;";
```

Nie ustawiaj `X-Frame-Options` — zablokowałby panel w iframe IdoSell.

W panelu dewelopera, zakładka **Technical data**, wpisz
(bez `https://` — panel dodaje go sam):

| Pole | Wartość |
|------|---------|
| How the application works | `Runs as an iframe` |
| Link to working application | `{APP_URL}/api/idosell/webhooks/launch` |
| Webhook address to enable the application | `{APP_URL}/api/idosell/webhooks/new-license` |
| Webhook address to disable the application | `{APP_URL}/api/idosell/webhooks/remove-license` |
| Webhook address to modify application licence | `{APP_URL}/api/idosell/webhooks/new-license` |

`php artisan idosell:install` wypisuje gotowe adresy dla Twojego `APP_URL`.

W tym samym miejscu zadeklaruj uprawnienia klucza Admin API — każda bramka API ma swoje uprawnienie,
zaznacz te, z których korzysta aplikacja.

## 9. Zainstaluj aplikację w sklepie testowym

Zgodnie z [instrukcją IdoSell](https://idosell.readme.io/docs/how-can-i-test-an-unpublished-application):

1. W panelu dewelopera: **Your data → Test panel ID or domain** — wpisz domenę swojego panelu demo.
2. W panelu demo: **Apps → Catalog of applications and services**, otwórz dowolną aplikację i w adresie
   strony zamień ID na ID swojej aplikacji.
3. Zamów aplikację i otwórz ją z menu panelu.

## 10. Jak sprawdzić, że działa

```bash
php artisan idosell:licenses --active
```

Na liście powinna być instalacja Twojego sklepu demo. Panel aplikacji otwiera się w panelu sklepu,
a „Pokaż sklepy” wyświetla sklepy z demo.

Coś nie działa? Zobacz [Typowe błędy](07-produkcja-i-bledy.md#typowe-błędy). Przed wydaniem aplikacji
przejdź [checklistę produkcyjną](07-produkcja-i-bledy.md#checklista-przed-produkcją).
