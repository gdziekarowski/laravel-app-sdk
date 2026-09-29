# Changelog

Format wg [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/);
wersjonowanie wg [SemVer](https://semver.org/lang/pl/).

## [Unreleased]

### Dodane

- Middleware `idosell.panel` (`EnsureIdosellLicense`): weryfikacja podpisanego URL panelu, wymóg aktywnej licencji, 403 (JSON dla `expectsJson()`).
- `Idosell::currentLicense()` oraz binding `IdosellLicense` w kontenerze i atrybut żądania `idosell_license` na trasach panelu.
- `Idosell::panelUrl()` i helper `idosell_route()` — podpisane linki z kontekstem instalacji do przenoszenia między stronami panelu w iframe.
- `Support\LaunchParameters` — wspólne mapowanie `idosell.launch.parameters` dla launch, middleware i linków panelu.
- Dokumentacja `docs/07-panel.md` (trasy, formularze, CSRF w iframe).

### Zmienione

- Usunięto `docs/06-pulapki.md`; `docs/07-migracja.md` → `docs/06-migracja.md`.
- Wsparcie Laravel 13 (`illuminate/*` `^13.0`, `orchestra/testbench` `^11.0`); matryca CI rozszerzona o Laravel 13.

## [0.1.0] — pierwsze wydanie

Pierwsze wydanie pakietu.

### Dodane

- Podpis `sign` z tolerancją daty (`SignatureService`).
- Deszyfracja `api_key` z odświeżeniem IV po nieudanej próbie (`ApiKeyDecryptor`).
- Klient Apps API: `installation/done`, `application/license`, `setPrice` (`AppsApiClient`).
- Klient Admin API sprzedawcy z autoryzacją `X-API-KEY` i OAuth Bearer (`AdminApiClient`).
- Webhooki cyklu życia: trasy, Form Requesty, kontroler, middleware weryfikacji i logowania.
- Model `IdosellLicense` z szyfrowanymi sekretami, fabryką i zakresami.
- Zdarzenia `LicenseActivated`, `LicenseDeactivating`, `LicenseDeactivated`, `AppLaunched`.
- Zasoby Admin API z idempotencją po tożsamości: `Snippets`, `OffersFeed`.
- Komendy `idosell:install`, `idosell:doctor`, `idosell:licenses`, `idosell:simulate`.
- Trait testowy `InteractsWithIdosell`.
- Baza wiedzy o IdoSell dla asystentów AI (`skills/`).
