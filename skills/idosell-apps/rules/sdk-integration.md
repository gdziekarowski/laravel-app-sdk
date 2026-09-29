# Integracja przez `idosell/laravel-app-sdk`

Jak zaimplementować mechanizmy IdoSell Apps w aplikacji Laravel, używając SDK. Najpierw sprawdź,
co paczka już robi — większość rzeczy z tego skilla jest w niej gotowa (Consistency First).

## Co daje paczka

| Element | Klasa / miejsce |
|---------|-----------------|
| Podpis `sign` | `Idosell\LaravelAppSdk\Services\SignatureService` |
| Deszyfracja `api_key` | `Services\ApiKeyDecryptor` |
| Apps API (licencje, `setPrice`, `installation/done`) | `Services\AppsApiClient` |
| Admin API sprzedawcy | `Services\AdminApiClient` |
| Webhooki cyklu życia | `Http\Controllers\WebhookController` + trasy z configu |
| Weryfikacja podpisu | middleware `idosell.verify-sign` |
| Log webhooków z redakcją sekretów | middleware `idosell.log-webhook` |
| Zapis instalacji | model `Models\IdosellLicense` (sekrety szyfrowane) |
| Punkty wpięcia logiki | zdarzenia `LicenseActivated`, `LicenseDeactivating`, `LicenseDeactivated`, `AppLaunched` |
| Diagnostyka | `php artisan idosell:doctor`, `idosell:simulate`, `idosell:licenses` |
| Pomocniki testowe | `Testing\InteractsWithIdosell` |

## Czego NIE pisz od nowa

- Własnego liczenia `sha256(login|data|klucz)` — użyj `Idosell::sign()` / `Idosell::verify()`.
- Własnego `openssl_decrypt` z `file_get_contents('…/keyset')` — to jest w `ApiKeyDecryptor`
  (z timeoutem, retry i odświeżeniem IV po nieudanej próbie).
- Własnego kontrolera webhooków — wystarczy nasłuchiwać zdarzeń.
- Własnej tabeli na `api_key` — model licencji ma casty `encrypted`.

## Konfiguracja i sekrety

- `config/idosell.php` (publikowalny: `php artisan idosell:install`).
- W `.env`: `IDOSELL_APPLICATION_ID`, `IDOSELL_DEVELOPER`, `IDOSELL_APPLICATION_KEY`,
  `IDOSELL_LAUNCH_ROUTE`. `IDOSELL_APPLICATION_KEY` to **sekret** — nigdy w repo.
- Nie hardkoduj domen sprzedawców. Adres Admin API wynika z `api_url` licencji, per instalacja.

## Logika aplikacji — wpinaj się zdarzeniami

```php
// app/Providers/AppServiceProvider.php
Event::listen(LicenseActivated::class, function (LicenseActivated $event): void {
    // Instalacja u sprzedawcy. Musi być idempotentne — $event->isNew bywa false.
    foreach ($event->license->shops() as $shop) {
        InstallInShop::dispatch($event->license, $shop['id']);
    }
});

Event::listen(LicenseDeactivating::class, function (LicenseDeactivating $event): void {
    // Ostatni moment z ważnym kluczem Admin API — sprzątaj TUTAJ i synchronicznie.
    // Listener nie może rzucać: nieudane sprzątanie nie może zablokować deaktywacji.
});
```

## Zasady, które łatwo przeoczyć

1. **Idempotencja** — webhooki przychodzą ponownie. `ActivateLicense` używa `updateOrCreate`;
   twoja logika też musi znieść powtórkę.
2. **Najpierw `sign`, potem cokolwiek innego** — robi to middleware; nie omijaj go własnymi trasami.
3. **Sprzątanie przed unieważnieniem licencji** — po deaktywacji klucz Admin API przestaje działać.
4. **Odpowiadaj szybko** — ciężką pracę wrzucaj do kolejki, z wyjątkiem sprzątania przy deaktywacji.
5. **Nie loguj payloadów** — `contact_data` to dane osobowe, `api_key`/`api_license` to sekrety.
   Do logowania używaj `Support\Redactor`.
6. **`QueryException` w logu** — nigdy nie loguj `getMessage()` wyjątku bazodanowego: zawiera SQL
   z podstawionymi wartościami, czyli sekretami. Loguj klasę wyjątku i kody błędu.

## Testowanie

```php
uses(InteractsWithIdosell::class);

it('instaluje aplikację u sprzedawcy', function () {
    $this->withIdosellConfig();
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense(['client_id' => 555001])
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});
```

Lokalnie bez panelu dewelopera: `php artisan idosell:simulate new-license` — wysyła webhook
o realnym kształcie, z prawdziwym podpisem i zaszyfrowanym `api_key`.
