# Komendy

## `idosell:install`

```bash
php artisan idosell:install [--migrations] [--skills] [--force]
```

Publikuje `config/idosell.php`, wypisuje brakujące zmienne `.env` i adresy webhooków.

- `--migrations`: publikuje migracje do `database/migrations` (ustaw wtedy `IDOSELL_RUN_MIGRATIONS=false`),
- `--skills`: publikuje bazę wiedzy o IdoSell dla asystentów AI do `.claude/skills`,
- `--force`: nadpisuje istniejące pliki.

## `idosell:doctor`

```bash
php artisan idosell:doctor [--live]
```

Sprawdza konfigurację, podpis, trasy webhooków, adres przekierowania po uruchomieniu i tabelę licencji.
`--live` łączy się dodatkowo z apps.idosell.com.

## `idosell:simulate`

```bash
php artisan idosell:simulate new-license --client=990001
php artisan idosell:simulate launch --client=990001
php artisan idosell:simulate remove-license --client=990001
```

Wysyła do aplikacji podpisany webhook i wypisuje odpowiedź. Bez argumentu pyta, który webhook wysłać.
Zablokowana przy `APP_ENV=production`.

| Opcja | Domyślnie |
|-------|-----------|
| `--client` | `990001` |
| `--application` | `IDOSELL_APPLICATION_ID` |
| `--api-url` | `https://demo-shop.example.com/api` |
| `--api-key` | `admin-api-key-0000000000000000` |
| `--api-license` | `LIC-SIMULATED-0000000000` |
| `--url` | `{APP_URL}/{IDOSELL_ROUTES_PREFIX}/{webhook}` |

Wymagania i skutki:

- aplikacja musi działać pod `APP_URL` (albo podaj `--url`),
- `new-license` pobiera IV z `IDOSELL_APPS_KEYSET_URL`, więc potrzebny jest dostęp do sieci,
- przy `IDOSELL_APP_TYPE=online` `new-license` wywołuje prawdziwe `installation/done` z testową
  licencją: licencja zostaje zapisana, ale odpowiedź to `error`, a `LicenseActivated` nie jest emitowane.
  Do przejścia całego przepływu lokalnie ustaw na czas testu `IDOSELL_APP_TYPE=downloadable`,
- `new-license` zapisuje licencję z wartościami opcji. Nie używaj `--client` istniejącej instalacji:
  nadpiszesz jej `api_url`, `api_key` i `api_license`.

## `idosell:licenses`

```bash
php artisan idosell:licenses [--active] [--client=]
```

Lista zapisanych instalacji bez sekretów.
