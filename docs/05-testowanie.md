# Testowanie integracji

## Trait `InteractsWithIdosell`

```php
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;

uses(InteractsWithIdosell::class);   // Pest
// albo: use InteractsWithIdosell;   — w klasie testu PHPUnit
```

| Metoda | Do czego |
|--------|----------|
| `withIdosellConfig([...])` | Testowy zestaw danych aplikacji (klucz 32-bajtowy) |
| `idosellSign(?$date)` | Prawidłowy podpis dla bieżącej konfiguracji |
| `idosellEncryptedApiKey($plain)` | `api_key` zaszyfrowany tak, jak robi to platforma |
| `fakeIdosellApps([...])` | Atrapa `keyset`, `installation/done`, `application/license` |
| `postIdosellNewLicense([...])` | Webhook aktywacji |
| `postIdosellRemoveLicense([...])` | Webhook deaktywacji |
| `postIdosellLaunch([...])` | Webhook uruchomienia |

Pomocniki `post*` idą **przez prawdziwą trasę HTTP**, razem z middleware. To celowe: większość
błędów integracji siedzi w warstwie żądania (podpis, walidacja, kolejność middleware), a nie
w samej akcji.

## Instalacja u sprzedawcy

```php
it('konfiguruje sklepy po instalacji aplikacji', function () {
    $this->withIdosellConfig();
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense([
        'client_id' => 555001,
        'selected_shops' => [['id' => 1, 'name' => 'Sklep 1'], ['id' => 2, 'name' => 'Sklep 2']],
    ])->assertOk()->assertJson(['status' => 'ok']);

    expect(MojaKonfiguracja::count())->toBe(2);
});
```

## Odrzucenie podrobionego webhooka

```php
it('nie ufa payloadowi bez ważnego podpisu', function () {
    $this->withIdosellConfig();

    $this->postIdosellNewLicense(['sign' => 'podrobiony'])
        ->assertOk()
        ->assertJson(['status' => 'error']);

    expect(Idosell::license(555001))->toBeNull();
});
```

## Sprzątanie przy odinstalowaniu

```php
it('usuwa snippet, póki klucz API jest jeszcze ważny', function () {
    $this->withIdosellConfig();
    $license = IdosellLicense::factory()->create(['client_id' => 555001]);

    Http::fake(['*snippets*' => Http::response(['results' => [['id' => 9001]]], 200)]);

    $this->postIdosellRemoveLicense(['client_id' => 555001])->assertJson(['status' => 'ok']);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE');
    expect($license->fresh()->active)->toBeFalse();
});
```

## Fabryka licencji

```php
IdosellLicense::factory()->create(['client_id' => 555001]);
IdosellLicense::factory()->inactive()->create();
IdosellLicense::factory()->oauth()->create();
IdosellLicense::factory()->withShops([['id' => 3, 'name' => 'Sklep 3']])->create();
```

## Atrapy Admin API

Admin API nie ma atrapy w paczce — każda aplikacja woła inne endpointy. Używaj `Http::fake()`:

```php
Http::fake(function ($request) {
    return $request->method() === 'GET'
        ? Http::response(['results' => []], 200)
        : Http::response(['results' => [['id' => 4001]]], 200);
});
Http::preventStrayRequests();
```

> Kolejne wywołania `Http::fake()` **dokładają** reguły, a pasuje pierwsza pasująca. Wspólna atrapa
> `'*'` w `beforeEach` przesłoni wszystko, co zarejestrujesz później w teście — w tym przypadki
> błędów. Rejestruj atrapy per test.

`Http::preventStrayRequests()` warto dodawać zawsze — inaczej test, który trafi w nieobsłużony
endpoint, po cichu wyjdzie do prawdziwego API.

## Symulacja webhooków lokalnie

```bash
php artisan idosell:simulate new-license --client=990001
php artisan idosell:simulate launch
php artisan idosell:simulate remove-license
```

Komenda buduje payload o realnym kształcie, podpisuje go i — dla `new-license` — szyfruje `api_key`
bieżącym IV z `keyset`, po czym wysyła żądanie HTTP pod własną aplikację. Przechodzi więc dokładnie
tę samą ścieżkę co prawdziwy webhook. Na produkcji jest zablokowana.

## Co warto pokryć testem

- [ ] Odrzucenie webhooka bez ważnego podpisu (i brak skutków ubocznych).
- [ ] Ponowne dostarczenie aktywacji nie duplikuje danych.
- [ ] Sprzątanie przy deaktywacji wykonuje się, póki licencja jest aktywna.
- [ ] Wejście do panelu aplikacji bez podpisanego URL kończy się 403.
- [ ] Błąd Admin API nie zostawia niespójnego stanu u Ciebie w bazie.
