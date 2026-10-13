# Panel aplikacji

Sprzedawca otwiera aplikację w panelu sklepu. SDK zwraca IdoSell podpisany link do Twojej trasy
z `IDOSELL_LAUNCH_ROUTE`, np. `https://twoja-aplikacja.example.com/panel?application=1234&client=5678&expires=…&signature=…`,
a IdoSell otwiera go w iframe.

## Trasy: middleware `idosell.panel`

Wszystkie strony panelu umieść w grupie z `idosell.panel`. Middleware wpuszcza tylko z ważnym
podpisanym linkiem i aktywną licencją, w innym wypadku zwraca 403.

```php
// routes/web.php
use App\Http\Controllers\PanelController;

Route::middleware('idosell.panel')->prefix('panel')->name('app.')->group(function (): void {
    Route::get('/', [PanelController::class, 'index'])->name('panel');          // IDOSELL_LAUNCH_ROUTE=app.panel
    Route::get('/ustawienia', [PanelController::class, 'settings'])->name('settings');
    Route::post('/sklepy', [PanelController::class, 'shops'])->name('shops');
});
```

Nie dodawaj `idosell.panel` do całej grupy `web` — strony otwierane spoza panelu IdoSell dostaną 403.

## Licencja sprzedawcy w kontrolerze

```php
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;

public function index(IdosellLicense $license): View      // wstrzyknięcie
{
    return view('panel', ['shops' => $license->shops()]);
}

public function settings(): View
{
    $license = Idosell::currentLicense();                 // albo przez fasadę

    return view('settings', ['license' => $license]);
}
```

`$license->shops()` to sklepy wybrane przez sprzedawcę przy instalacji, `$license->adminApi()` — klient
Admin API jego sklepu ([05-admin-api.md](05-admin-api.md)).

## Linki i formularze

Każdy link i formularz w panelu musi nieść podpis — buduj adresy przez `idosell_route()`:

```blade
<a href="{{ idosell_route('app.settings') }}">Ustawienia</a>
<a href="{{ idosell_route('app.panel', ['tab' => 'raport']) }}">Raport</a>

<form method="POST" action="{{ idosell_route('app.shops') }}">
    <button type="submit">Pokaż sklepy</button>
</form>
```

- Zwykły `route()` albo `url()` da link bez podpisu → 403.
- Formularze wysyłaj metodą **POST**. Formularz GET nadpisuje adres polami formularza i gubi podpis.
- Linki są ważne 30 minut (`IDOSELL_LAUNCH_TTL`). Potem sprzedawca otwiera aplikację ponownie z panelu.
- W PHP: `Idosell::panelUrl('app.settings')`.

## CSRF

W iframe na innej domenie cookies zwykle nie docierają, więc formularze POST dostałyby 419. Wyłącz CSRF dla panelu —
chroni go podpisany link:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['panel', 'panel/*']);
})
```

## Proxy i https

Podpis obejmuje schemat i host adresu. Jeśli aplikacja stoi za proxy lub load balancerem z TLS, dodaj:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
})
```

Bez tego podpis się nie zgodzi i panel zwróci 403.

## Osadzanie w iframe i nagłówki

- Pozwól osadzać panel tylko w panelu IdoSell — nagłówkiem
  `Content-Security-Policy: frame-ancestors 'self' https://*.idosell.com https://*.iai-shop.com`.
  Nie ustawiaj `X-Frame-Options` — zablokowałby panel w iframe IdoSell.
- SDK ustawia na stronach panelu `Referrer-Policy: no-referrer`, żeby podpisany link nie wyciekał do
  innych stron. **Nie dodawaj w layoucie `<meta name="referrer">` ani własnego nagłówka
  `Referrer-Policy`** — nadpiszą to ustawienie.
