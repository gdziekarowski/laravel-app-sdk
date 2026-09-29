# Panel aplikacji w iframe

Panel sprzedawcy IdoSell osadza aplikację w iframe z innej domeny. Cookies sesji bywają wtedy
blokowane (SameSite, Safari ITP), więc kontekst sprzedawcy nie może żyć w sesji. SDK przenosi go
w **podpisanym URL czasowym** — tym samym, który zwraca webhook `launch`:

```
https://moja-aplikacja.example.com/panel?application=4242&client=555001&expires=...&signature=...
```

Middleware `idosell.panel` zamyka całą obsługę takiego wejścia.

## Middleware `idosell.panel`

Klasa: `Idosell\LaravelAppSdk\Http\Middleware\EnsureIdosellLicense`. Alias rejestruje provider SDK,
bez konfiguracji w aplikacji. Middleware:

1. weryfikuje podpis URL (`$request->hasValidSignature()`),
2. odczytuje `client` i `application` **wyłącznie z query stringu**, wg mapy
   `idosell.launch.parameters`; bez `application` w URL używa `idosell.apps.application_id`,
3. wymaga **aktywnej** licencji tej pary (`client_id`, `application_id`),
4. udostępnia licencję: `Idosell::currentLicense()`, atrybut żądania `idosell_license`
   (`EnsureIdosellLicense::ATTRIBUTE`) i binding `IdosellLicense` w kontenerze.

Zły lub wygasły podpis, brak kontekstu, brak licencji, licencja nieaktywna — zawsze `403`.
Dla `expectsJson()` odpowiedź to `{"message": "..."}`.

Podpis obejmuje tylko URL, nie body. Dlatego POST formularza wysłany na podpisany adres przechodzi
weryfikację, a pola formularza nie mogą nadpisać kontekstu (czytamy tylko query string).

## Trasy

```php
use Idosell\LaravelAppSdk\Http\Middleware\EnsureIdosellLicense;

// routes/web.php — alias
Route::get('/panel', PanelController::class)->middleware('idosell.panel')->name('app.panel');

// po klasie
Route::get('/panel', PanelController::class)->middleware(EnsureIdosellLicense::class);

// grupa
Route::middleware('idosell.panel')->prefix('panel')->name('panel.')->group(function (): void {
    Route::get('/', PanelController::class)->name('home');
    Route::post('/shops/fetch', FetchShopsController::class)->name('shops.fetch');
});
```

Trasa z `IDOSELL_LAUNCH_ROUTE` powinna mieć `idosell.panel` zamiast samego `signed`. Przy
`IDOSELL_LAUNCH_SIGNED=false` launch zwraca URL bez podpisu — middleware go odrzuci.

Można dopiąć middleware do całej grupy w `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->appendToGroup('web', 'idosell.panel');
})
```

Uwaga: wtedy obejmuje **każdą** trasę grupy `web`, także strony bez kontekstu sprzedawcy (strona
główna, health check, strony publiczne) — dostaną 403. Bezpieczniej użyć osobnej grupy tras.

## Kontroler

```php
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;

class PanelController
{
    public function __invoke(): View
    {
        $license = Idosell::currentLicense();

        return view('panel', ['shops' => $license->shops()]);
    }
}

// albo wstrzyknięcie z kontenera
public function __invoke(IdosellLicense $license): View { /* ... */ }
```

`currentLicense()` poza trasą z `idosell.panel` (konsola, kolejka, inne trasy) zwraca `null`.

## Kolejne linki i formularze

Każdy link i akcja formularza w panelu musi nieść kontekst — inaczej następne żądanie dostanie 403.

```php
Idosell::panelUrl('panel.shops.fetch');                          // bieżąca licencja
Idosell::panelUrl('panel.report', ['month' => '2026-09']);       // dodatkowe parametry
Idosell::panelUrl('panel.home', [], $license);                   // jawna licencja (np. w mailu, jobie)
idosell_route('panel.shops.fetch');                              // helper, ta sama sygnatura
```

Parametry kontekstu (`client`, `application`) nadpisują te podane w `$parameters`. TTL z
`idosell.launch.ttl` (domyślnie 30 min) — liczony od wygenerowania strony, więc sprzedawca, który
zostawi kartę otwartą dłużej, musi uruchomić aplikację ponownie z panelu sklepu. Bez licencji
i poza trasą panelu `panelUrl()` rzuca `IdosellException`.

```blade
<a href="{{ idosell_route('panel.report', ['month' => '2026-09']) }}">Raport</a>

<form method="POST" action="{{ idosell_route('panel.shops.fetch') }}">
    <button type="submit">Pobierz sklepy</button>
</form>
```

Formularze GET: parametry z `<input>` zastępują query string akcji, więc podpis i kontekst zginą.
Używaj POST albo linków z `idosell_route()`.

## CSRF

`VerifyCsrfToken` opiera się na sesji, a ta w iframe bez cookies nie działa — POST z panelu
dostanie `419`. SDK świadomie **nie** wyłącza CSRF automatycznie. Opcje:

- **Wyjątek CSRF dla tras panelu** (zalecane przy braku cookies). Podpisany, czasowy URL wiąże
  żądanie z konkretną instalacją i nie da się go podrobić bez `APP_KEY`, więc pełni rolę tokenu
  CSRF:

  ```php
  // bootstrap/app.php
  ->withMiddleware(function (Middleware $middleware): void {
      $middleware->validateCsrfTokens(except: ['panel/*']);
  })
  ```

  Ograniczenie: kto przechwyci ważny URL (np. z historii przeglądarki, logów proxy), może go
  użyć do upływu TTL. Trzymaj krótki TTL i nie loguj pełnych adresów z `signature`.

- **Trasy panelu poza grupą `web`** (np. własna grupa bez sesji i CSRF) — ten sam efekt,
  bez listy wyjątków.

- **Sesja z `SameSite=None; Secure`** (`SESSION_SAME_SITE=none`, `SESSION_SECURE_COOKIE=true`) —
  CSRF działa normalnie tam, gdzie przeglądarka przepuszcza cookies stron trzecich. Safari i
  przeglądarki z blokadą cookies stron trzecich nadal ich nie wyślą, więc to nie jest rozwiązanie
  samodzielne.

## Proxy i adres aplikacji

Podpis jest liczony z pełnego URL (schemat, host, ścieżka). Za load balancerem / proxy TLS
skonfiguruj `TrustProxies`, inaczej aplikacja zobaczy `http://` zamiast `https://` i każdy podpis
będzie nieważny.

## Testy

```php
it('pokazuje panel sprzedawcy', function () {
    $license = IdosellLicense::factory()->create(['client_id' => 555001]);

    $this->get(Idosell::panelUrl('app.panel', [], $license))->assertOk();
});

it('odrzuca wejście bez podpisu', function () {
    $this->get(route('app.panel', ['client' => 555001]))->assertForbidden();
});
```
