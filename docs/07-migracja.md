# Migracja z własnej implementacji

## Odpowiedniki

| Własna implementacja | SDK |
|----------------------|-----|
| `hash('sha256', $login.'|'.date('Y-m-d').'|'.$key)` | `Idosell::sign()` |
| weryfikacja `sign` w kontrolerze | middleware `idosell.verify-sign` |
| `openssl_decrypt` + pobranie `keyset` | `Idosell::decryptApiKey()` (aktywacja robi to sama) |
| kontroler webhooków | trasy i `WebhookController` pakietu |
| model licencji | `Models\IdosellLicense` |
| `Http::withHeaders(['X-API-KEY' => ...])` | `$license->adminApi()` |
| `POST application/installation/done` | wywoływane przy aktywacji |
| weryfikacja podpisanego URL panelu | middleware `idosell.panel` |

## 1. Instalacja obok istniejącego kodu

```bash
composer require idosell/laravel-app-sdk
php artisan idosell:install
php artisan migrate
```

```dotenv
IDOSELL_ROUTES_ENABLED=false
```

## 2. Przeniesienie licencji

Zapisuj przez model (szyfruje `api_key` i `api_license`), nie przez `DB::insert()`. `api_key` podawaj
odszyfrowany.

```php
use Idosell\LaravelAppSdk\Models\IdosellLicense;

public function up(): void
{
    StaraLicencja::query()->each(function (StaraLicencja $old): void {
        IdosellLicense::updateOrCreate(
            ['client_id' => $old->client_id, 'application_id' => $old->application_id],
            [
                'api_url' => $old->api_url,
                'api_license' => $old->api_license,
                'api_key' => $old->api_key,
                'authorization_type' => $old->auth_type ?? 'key',
                'active' => (bool) $old->active,
                'installation_confirmed' => true,
                'meta' => ['selected_shops' => $old->shops],   // [['id' => 1, 'name' => 'Sklep 1'], ...]
            ],
        );
    });
}
```

Przed uruchomieniem zrób kopię tabeli i przetestuj migrację na kopii bazy.

## 3. Logika do listenerów

- kod wykonywany po zapisaniu licencji → listener `LicenseActivated`,
- sprzątanie zasobów w sklepie → listener `LicenseDeactivating` (synchronicznie, przed wyłączeniem licencji).

Zob. [03-zdarzenia.md](03-zdarzenia.md).

## 4. Przełączenie tras

Adresy webhooków zostają te same, jeśli ustawisz ich prefiks zamiast zmieniać adresy w panelu dewelopera:

```dotenv
IDOSELL_ROUTES_ENABLED=true
IDOSELL_ROUTES_PREFIX=api/webhooks/idosell
```

Inne ścieżki: [01-konfiguracja.md](01-konfiguracja.md#własne-trasy-webhooków).

## 5. Weryfikacja

```bash
php artisan idosell:doctor --live
php artisan idosell:licenses
```

Pełny przepływ sprawdź na koncie testowym IdoSell albo na `client_id`, który nie istnieje w bazie
(`idosell:simulate`, zob. [06-komendy.md](06-komendy.md)).
