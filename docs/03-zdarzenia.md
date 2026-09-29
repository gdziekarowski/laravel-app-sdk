# Zdarzenia

Webhooki obsługuje pakiet. Własną logikę podpinasz listenerami zdarzeń.

| Webhook | Zdarzenie | Właściwości | Kiedy |
|---------|-----------|-------------|-------|
| `new-license` | `LicenseActivated` | `license`, `payload`, `isNew` | licencja zapisana i (dla `online`) potwierdzona w IdoSell |
| `remove-license` | `LicenseDeactivating` | `license` | przed wyłączeniem licencji, klucz Admin API jeszcze działa |
| `remove-license` | `LicenseDeactivated` | `license`, `deleted` | licencja wyłączona (`deleted = true` przy `IDOSELL_ON_DEACTIVATION=delete`) |
| `launch` | `AppLaunched` | `payload`, `redirect` | zwrócono link do panelu |

Klasy zdarzeń: `Idosell\LaravelAppSdk\Events\*`.

## Rejestracja listenerów

Klasa w `app/Listeners` z metodą `handle(LicenseActivated $event)` jest rejestrowana automatycznie.
Nie dodawaj jej dodatkowo przez `Event::listen()`, bo wykona się dwa razy. `Event::listen()` w
`AppServiceProvider::boot()` stosuj dla closure albo klas spoza `app/Listeners`.

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

- Webhook przychodzi ponownie przy reinstalacji i ponownym dostarczeniu (`isNew = false`). Listener musi
  znieść powtórzenie; `ensureCampaign()` i `upsert()` nie tworzą duplikatów.
- Wyjątek w listenerze kończy webhook odpowiedzią `error` (licencja zostaje zapisana).
- Gdy potwierdzenie `installation/done` się nie powiedzie, licencja jest zapisana, ale zdarzenie
  nie jest emitowane.
- `payload` zawiera `api_license` i dane kontaktowe sprzedawcy. Nie loguj go i nie przekazuj do jobów.

## Odinstalowanie: `LicenseDeactivating`

Po odinstalowaniu klucz Admin API przestaje działać, więc zasoby w sklepie usuwaj w tym zdarzeniu:

- synchronicznie (bez `ShouldQueue`),
- bez rzucania wyjątku: wyjątek zostawia licencję aktywną i kończy webhook odpowiedzią `error`.

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

Karencja zamiast usunięcia: IdoSell wyłączy snippet sam we wskazanym dniu.

```php
$snippetId = $snippets->find('Mój skrypt', $campaignId);
$snippets->setEndDate($snippetId, now()->addDays(90)->format('Y-m-d'));
```

Przy ponownej instalacji anuluj datę: `$snippets->setEndDate($snippetId, null)`.

## Po odinstalowaniu: `LicenseDeactivated`

Przy `IDOSELL_ON_DEACTIVATION=delete` rekord licencji jest już usunięty z bazy; `license` zawiera
ostatni stan. Przy `mark_inactive` licencja zostaje z `active = false`.

## Uruchomienie: `AppLaunched`

Do statystyk lub audytu wejść. `payload`: `client_id`, `application_id`, `api_url`, `api_license`.
`redirect` to podpisany link do panelu; nie loguj go.

## OAuth

Przy `authorization_type = OAuth` pakiet nie zapisuje tokenu (`api_key = null`). Aplikacja pobiera
token sama (endpoint `authorize/accessToken` Admin API sklepu) i zapisuje go w licencji, np. w
listenerze `LicenseActivated`:

```php
$event->license->update(['api_key' => $accessToken]);
```

`adminApi()` wysyła wtedy `Authorization: Bearer {api_key}`. Odświeżanie tokenu jest po stronie aplikacji.
