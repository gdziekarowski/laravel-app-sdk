# Testowanie

## Setup

```php
// tests/Pest.php
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class, InteractsWithIdosell::class)
    ->in('Feature');
```

PHPUnit: `use RefreshDatabase, InteractsWithIdosell;` w klasie testu. Tabela licencji powstaje z migracji
pakietu.

## Trait `InteractsWithIdosell`

| Metoda | Działanie |
|--------|-----------|
| `withIdosellConfig(array $overrides = [])` | testowa konfiguracja: `application_id` 4242, `developer` `dev-login`, klucz 32 × `K`, typ `online` |
| `fakeIdosellApps(array $extra = [])` | atrapa `keyset`, `installation/done`, `application/license` + `Http::preventStrayRequests()`; `$extra` to dodatkowe reguły `Http::fake()` |
| `postIdosellNewLicense(array $payload = [])` | webhook aktywacji: `client_id` 555001, `api_url` `https://demo-shop.example.com/api`, sklep 1 |
| `postIdosellRemoveLicense(array $payload = [])` | webhook deaktywacji |
| `postIdosellLaunch(array $payload = [])` | webhook uruchomienia |
| `idosellSign(?string $date = null)` | prawidłowy `sign` |
| `idosellEncryptedApiKey(string $plain)` | `api_key` zaszyfrowany jak przez IdoSell |
| `idosellIv()` | IV użyty przez atrapę `keyset` |

`postIdosell*` wysyłają żądania na trasy `idosell.webhooks.*`. `$payload` nadpisuje pola domyślne.

## Fabryka

```php
use Idosell\LaravelAppSdk\Models\IdosellLicense;

IdosellLicense::factory()->create(['client_id' => 555001]);
IdosellLicense::factory()->inactive()->create();
IdosellLicense::factory()->oauth()->create();
IdosellLicense::factory()->withShops([['id' => 3, 'name' => 'Sklep 3']])->create();
```

## Przykłady

```php
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Support\Facades\Http;

it('zapisuje licencję po instalacji', function () {
    $this->withIdosellConfig();
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense(['client_id' => 555001])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect(Idosell::license(555001)?->active)->toBeTrue();
});

it('odrzuca webhook z błędnym podpisem', function () {
    $this->withIdosellConfig();

    $this->postIdosellNewLicense(['sign' => 'podrobiony'])->assertJson(['status' => 'error']);

    expect(Idosell::license(555001))->toBeNull();
});

it('wpuszcza do panelu z podpisanym linkiem', function () {
    $this->withIdosellConfig();
    $license = IdosellLicense::factory()->create(['client_id' => 555001]);

    $this->get(Idosell::panelUrl('app.panel', [], $license))->assertOk();
    $this->get(route('app.panel', ['client' => 555001]))->assertForbidden();
});

it('usuwa kampanię przy odinstalowaniu', function () {
    // wymaga listenera CleanUpForMerchant z docs/03-zdarzenia.md
    $this->withIdosellConfig();
    IdosellLicense::factory()->create(['client_id' => 555001]);

    Http::fake([
        '*snippets/campaign*' => function ($request) {
            return $request->method() === 'GET'
                ? Http::response(['results' => [['id' => 7, 'name' => 'Moja aplikacja', 'shop' => [1]]]])
                : Http::response(['results' => [['id' => 7]]]);
        },
    ]);

    $this->postIdosellRemoveLicense(['client_id' => 555001])->assertJson(['status' => 'ok']);

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE');
    expect(Idosell::license(555001)->active)->toBeFalse();
});
```

Trasa `app.panel` pochodzi z aplikacji ([02-panel.md](02-panel.md)).
