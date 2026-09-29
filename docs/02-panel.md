# Panel aplikacji

Sprzedawca otwiera aplikację z panelu IdoSell. Webhook `launch` zwraca podpisany link do trasy
z `IDOSELL_LAUNCH_ROUTE`, np. `https://twoja-aplikacja.example.com/panel?application=4242&client=555001&expires=...&signature=...`.

## Middleware `idosell.panel`

Wpuszcza, gdy link ma ważny podpis i istnieje aktywna licencja tej instalacji. W każdym innym
przypadku zwraca 403 (dla żądań JSON: `{"message": "..."}`). Klasa:
`Idosell\LaravelAppSdk\Http\Middleware\EnsureIdosellLicense`.

```php
// routes/web.php
use App\Http\Controllers\PanelController;

Route::middleware('idosell.panel')->prefix('panel')->name('app.')->group(function (): void {
    Route::get('/', [PanelController::class, 'index'])->name('panel');          // IDOSELL_LAUNCH_ROUTE=app.panel
    Route::get('/ustawienia', [PanelController::class, 'settings'])->name('settings');
    Route::post('/sklepy', [PanelController::class, 'fetchShops'])->name('shops.fetch');
});
```

Nie dopinaj `idosell.panel` do całej grupy `web` (`appendToGroup`): strony bez linku z panelu IdoSell
dostaną 403.

## Licencja w kontrolerze

```php
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;

public function index(IdosellLicense $license): View
{
    return view('panel', ['shops' => $license->shops()]);
}

public function settings(): View
{
    return view('settings', ['license' => Idosell::currentLicense()]);
}
```

Poza trasą z `idosell.panel` `Idosell::currentLicense()` zwraca `null`.

## Linki i formularze

Każdy link i formularz w panelu musi prowadzić na podpisany adres, inaczej następne żądanie dostanie 403.

```blade
<a href="{{ idosell_route('app.settings') }}">Ustawienia</a>
<a href="{{ idosell_route('app.panel', ['tab' => 'raport']) }}">Raport</a>

<form method="POST" action="{{ idosell_route('app.shops.fetch') }}">
    <button type="submit">Pokaż sklepy</button>
</form>
```

W PHP: `Idosell::panelUrl('app.settings', ['tab' => 'raport'])`; z jawną licencją:
`Idosell::panelUrl('app.panel', [], $license)`. Bez licencji i poza trasą panelu rzuca `IdosellException`.

- Linki są ważne `IDOSELL_LAUNCH_TTL` minut (domyślnie 30) od wyrenderowania strony. Po tym czasie
  sprzedawca uruchamia aplikację ponownie z panelu IdoSell.
- Formularze wysyłaj metodą POST. Formularz GET zastępuje query string akcji polami formularza
  i gubi podpis.
- Pola formularza nie zmieniają sklepu ani aplikacji: kontekst jest brany tylko z podpisanego adresu.

## CSRF

W iframe panelu IdoSell cookies sesji zwykle nie docierają, więc `VerifyCsrfToken` odrzuci POST
kodem 419. Wyłącz CSRF dla tras panelu (chroni je podpisany link z terminem ważności):

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['panel', 'panel/*']);
})
```

Alternatywa: zarejestruj trasy panelu poza grupą `web`, np. w osobnym pliku tras bez sesji i CSRF.

## Nagłówki

Podpisany link działa jak hasło do czasu wygaśnięcia. W layoutcie panelu ustaw politykę Referer,
żeby link nie trafiał do zewnętrznych zasobów:

```blade
<meta name="referrer" content="same-origin">
```

Jeśli aplikacja ustawia `X-Frame-Options` albo CSP `frame-ancestors`, zezwól na osadzanie w panelu IdoSell.

## Proxy

Podpis obejmuje schemat i host. Za load balancerem lub proxy TLS:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
})
```

## Własny adres po uruchomieniu

Stały adres zamiast trasy: `IDOSELL_LAUNCH_URL=https://twoja-aplikacja.example.com/start`
(bez podpisu, więc bez `idosell.panel`).

Własna logika w `AppServiceProvider::boot()`:

```php
Idosell::resolveLaunchUrlUsing(
    fn (array $payload): string => URL::temporarySignedRoute('app.welcome', now()->addMinutes(5), [
        'client' => $payload['client_id'],
        'application' => $payload['application_id'],
    ]),
);
```

`$payload` to zwalidowany webhook `launch`: `client_id`, `application_id`, `api_url`, `api_license`, `sign`.
