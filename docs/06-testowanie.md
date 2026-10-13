# Testowanie

## Testy automatyczne

SDK daje trait `InteractsWithIdosell`: wysyła do aplikacji webhooki takie jak IdoSell (z poprawnym
podpisem) i podstawia atrapy API IdoSell.

```php
// tests/Pest.php
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class, InteractsWithIdosell::class)
    ->in('Feature');
```

W PHPUnit: `use RefreshDatabase, InteractsWithIdosell;` w klasie testu.

| Metoda | Działanie |
|--------|-----------|
| `withIdosellConfig()` | testowa konfiguracja aplikacji (wywołaj na początku testu) |
| `fakeIdosellApps()` | atrapy API IdoSell; licencja należy do klienta `555001` (inny: `fakeIdosellApps(clientId: 123)`) |
| `postIdosellNewLicense([...])` | webhook instalacji (domyślnie klient `555001`) |
| `postIdosellLaunch([...])` | webhook uruchomienia — wymaga wcześniej aktywnej licencji |
| `postIdosellRemoveLicense([...])` | webhook odinstalowania |

Webhooki używają domyślnie klucza licencji `LIC-TEST-0000000000000000`. Tworząc licencję fabryką do testu
`launch` albo `remove-license`, podaj ten sam klucz.

```php
use Idosell\LaravelAppSdk\Models\IdosellLicense;

IdosellLicense::factory()->create(['client_id' => 555001, 'api_license' => 'LIC-TEST-0000000000000000']);
IdosellLicense::factory()->inactive()->create();
IdosellLicense::factory()->withShops([['id' => 3, 'name' => 'Sklep 3']])->create();
```

### Przykłady

```php
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Support\Facades\Http;

it('zapisuje licencję po instalacji', function () {
    $this->withIdosellConfig();
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense()->assertJson(['status' => 'ok']);

    expect(Idosell::license(555001)?->active)->toBeTrue();
});

it('odrzuca webhook z błędnym podpisem', function () {
    $this->withIdosellConfig();

    $this->postIdosellNewLicense(['sign' => 'podrobiony'])->assertJson(['status' => 'error']);

    expect(Idosell::license(555001))->toBeNull();
});

it('wpuszcza do panelu tylko z podpisanym linkiem', function () {
    $this->withIdosellConfig();
    $license = IdosellLicense::factory()->create(['client_id' => 555001]);

    $this->get(Idosell::panelUrl('app.panel', [], $license))->assertOk();
    $this->get(route('app.panel', ['client' => 555001]))->assertForbidden();
});

it('usuwa kampanię przy odinstalowaniu', function () {
    // wymaga listenera CleanUpForMerchant z 04-zdarzenia.md
    $this->withIdosellConfig();
    IdosellLicense::factory()->create(['client_id' => 555001, 'api_license' => 'LIC-TEST-0000000000000000']);

    Http::fake([
        '*snippets/campaign*' => fn ($request) => $request->method() === 'GET'
            ? Http::response(['results' => [['id' => 7, 'name' => 'Moja aplikacja', 'shop' => [1]]]])
            : Http::response(['results' => [['id' => 7]]]),
    ]);

    $this->postIdosellRemoveLicense()->assertJson(['status' => 'ok']);

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE');
    expect(Idosell::license(555001)->active)->toBeFalse();
});
```

## Test lokalny z symulatorem

`php artisan idosell:simulate` wysyła webhooki do działającej lokalnie aplikacji. Przepis krok po kroku:
[01-pierwsza-aplikacja.md, krok 7](01-pierwsza-aplikacja.md#7-test-lokalny-bez-panelu-idosell).
Wszystkie opcje komendy: [08-referencja.md](08-referencja.md#idosellsimulate).
