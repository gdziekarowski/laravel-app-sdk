# Zdarzenia

Webhooki obsługuje SDK. Twój kod podpinasz listenerami zdarzeń.

| Zdarzenie | Kiedy | Do czego |
|-----------|-------|----------|
| `LicenseActivated` | po instalacji (i ponownej instalacji / zmianie licencji) | konfiguracja u sprzedawcy |
| `LicenseDeactivating` | przy odinstalowaniu, **zanim** licencja zostanie wyłączona | sprzątanie w sklepie |
| `LicenseDeactivated` | po wyłączeniu licencji | porządki w Twojej bazie |
| `AppLaunched` | sprzedawca otworzył aplikację | statystyki wejść |

Klasy: `Idosell\LaravelAppSdk\Events\*`. Listener w `app/Listeners` z metodą `handle(...)` rejestruje się
sam — nie dodawaj go drugi raz przez `Event::listen()`, bo wykona się dwukrotnie.

## Instalacja: `LicenseActivated`

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

- **Musi znieść ponowne wywołanie.** To samo zdarzenie przychodzi przy reinstalacji i zmianie licencji
  (`$event->isNew === false`). `ensureCampaign()` i `upsert()` nie tworzą duplikatów.
- **Wyjątek w listenerze** kończy webhook odpowiedzią `error`, choć licencja jest już zapisana i aktywna.
  Łap błędy i zgłaszaj je (`report($e)`), żeby nie zostawić instalacji w połowie.
- Ciężką pracę wrzucaj do kolejki — IdoSell czeka na odpowiedź webhooka.
- `$event->payload` zawiera klucz licencji i dane kontaktowe sprzedawcy — nie loguj go.

## Odinstalowanie: `LicenseDeactivating`

To ostatni moment, w którym klucz Admin API sprzedawcy działa. Usuń tu to, co aplikacja dodała w sklepie:

- **synchronicznie** (bez `ShouldQueue`) — po odinstalowaniu klucz przestaje działać,
- **bez rzucania wyjątku** — wyjątek przerywa odinstalowanie i licencja zostaje aktywna.

```php
namespace App\Listeners;

use Idosell\LaravelAppSdk\Events\LicenseDeactivating;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Throwable;

class CleanUpForMerchant
{
    public function handle(LicenseDeactivating $event): void
    {
        $snippets = Idosell::snippets($event->license);

        foreach ($event->license->shops() as $shop) {
            try {
                $campaignId = $snippets->findCampaign('Moja aplikacja', $shop['id']);

                if ($campaignId !== null) {
                    $snippets->deleteCampaign($campaignId);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
```

Zamiast usuwać od razu, możesz wyłączyć snippet z opóźnieniem — IdoSell zrobi to sam we wskazanym dniu:

```php
$snippetId = $snippets->find('Mój skrypt', $campaignId);
$snippets->setEndDate($snippetId, now()->addDays(90)->format('Y-m-d'));   // null anuluje (np. przy reinstalacji)
```

## Po odinstalowaniu: `LicenseDeactivated`

Licencja zostaje w bazie z `active = false`. Przy `IDOSELL_ON_DEACTIVATION=delete` rekord jest usuwany,
a `$event->license` zawiera jego ostatni stan.

## Uruchomienie: `AppLaunched`

Do statystyk wejść. `$event->payload` zawiera m.in. `client_id`, klucz licencji i podpis `sign`,
a `$event->redirect` — podpisany link do panelu. Żadnego z nich nie loguj.

## OAuth

Jeśli aplikacja w panelu dewelopera używa autoryzacji OAuth (`authorization_type = OAuth`), SDK nie
zapisuje tokenu. Pobierz go sam (endpoint `authorize/accessToken` Admin API sklepu) i zapisz w licencji,
np. w listenerze `LicenseActivated`:

```php
$event->license->update(['api_key' => $accessToken]);
```

`adminApi()` wyśle wtedy `Authorization: Bearer …`. Odświeżanie tokenu jest po Twojej stronie.
