# Migracja z własnej implementacji

Przewodnik dla aplikacji, które mają już własną obsługę IdoSell Apps i chcą przejść na SDK.

## Mapa odpowiedników

| Twoja implementacja | Odpowiednik w SDK |
|---------------------|-------------------|
| `hash('sha256', $login.'|'.date('Y-m-d').'|'.$key)` | `Idosell::sign()` |
| ręczne `hash_equals` na `sign` | middleware `idosell.verify-sign` |
| `openssl_decrypt` + `file_get_contents('…/keyset')` | `Idosell::decryptApiKey()` |
| własny `WebhookController` | trasy i kontroler z paczki |
| własny model licencji / wpisy w configu | `Models\IdosellLicense` |
| `Http::withHeaders(['X-API-KEY' => ...])` | `$license->adminApi()` |
| ręczne `POST /application/installation/done` | `ActivateLicense` (automatycznie) |
| własne logowanie webhooków | middleware `idosell.log-webhook` |

## Krok 1: instalacja obok istniejącego kodu

```bash
composer require idosell/laravel-app-sdk
php artisan vendor:publish --tag=idosell-config
```

Wyłącz na razie trasy paczki, żeby nie kolidowały z Twoimi:

```dotenv
IDOSELL_ROUTES_ENABLED=false
```

## Krok 2: przeniesienie danych licencji

Jeśli trzymasz instalacje we własnej tabeli, przepisz je migracją. Pamiętaj, że `api_key`
i `api_license` w modelu SDK są szyfrowane — zapisuj **przez model**, nie `DB::insert()`:

```php
public function up(): void
{
    StaraLicencja::query()->each(function (StaraLicencja $stara): void {
        IdosellLicense::updateOrCreate(
            ['client_id' => $stara->client_id, 'application_id' => $stara->application_id],
            [
                'api_url' => $stara->api_url,
                'api_license' => $stara->api_license,     // jawnie — cast zaszyfruje
                'api_key' => $stara->api_key,
                'authorization_type' => $stara->auth_type ?? 'key',
                'active' => (bool) $stara->active,
                'installation_confirmed' => true,
                'meta' => ['selected_shops' => $stara->shops],
            ],
        );
    });
}
```

Migracji nie da się odwrócić bez utraty danych — zrób kopię tabeli przed uruchomieniem
i przetestuj ją najpierw na kopii bazy.

## Krok 3: przeniesienie logiki do zdarzeń

To, co robił Twój kontroler po zapisaniu licencji, przenieś do listenera:

```php
// było: w kontrolerze webhooka, po $this->zapiszLicencje($payload)
// jest:
Event::listen(LicenseActivated::class, function (LicenseActivated $event): void {
    // ...
});
```

Logikę sprzątania przenieś do `LicenseDeactivating` — **przed** unieważnieniem licencji.
Jeśli wcześniej sprzątałeś po zapisaniu `active = false`, prawdopodobnie robiłeś to już bez
ważnego klucza API i część zasobów została w panelach sprzedawców. Warto to sprawdzić.

## Krok 4: przełączenie tras

Gdy testy przechodzą, usuń własne trasy webhooków i włącz te z paczki:

```dotenv
IDOSELL_ROUTES_ENABLED=true
```

Jeżeli Twoje adresy różnią się od domyślnych, zachowaj je zamiast zmieniać konfigurację w panelu
dewelopera (zmiana adresu webhooka dotyczy wszystkich istniejących instalacji):

```php
// config/idosell.php
'routes' => ['prefix' => 'api/webhooks/idosell'],
```

Albo zostaw `enabled = false` i zarejestruj własne trasy na kontrolerze z paczki:

```php
Route::middleware(['api', 'idosell.log-webhook', 'idosell.verify-sign'])
    ->prefix('moje/webhooki')
    ->name('idosell.webhooks.')
    ->group(function () {
        Route::post('new-license', [WebhookController::class, 'newLicense'])->name('new-license');
        Route::post('remove-license', [WebhookController::class, 'removeLicense'])->name('remove-license');
        Route::post('launch', [WebhookController::class, 'launch'])->name('launch');
    });
```

> Nazwy tras zachowaj z prefiksem `idosell.webhooks.` — używa ich `idosell:doctor` i pomocniki
> testowe.

## Krok 5: weryfikacja

```bash
php artisan idosell:doctor --live
php artisan idosell:licenses
php artisan idosell:simulate new-license --client=<istniejący client_id>
```

Ostatnia komenda przejdzie pełną ścieżką na istniejącej instalacji — sprawdzisz, czy ponowna
aktywacja nie psuje danych (powinna być idempotentna).

## Czego nie migrować

- Własnej logiki biznesowej — SDK jej nie zastępuje.
- Odbioru webhooków zdarzeń Admin API (`orderCreated` itp.) — to osobny mechanizm, poza paczką.
- Warstwy prezentacji panelu aplikacji.
