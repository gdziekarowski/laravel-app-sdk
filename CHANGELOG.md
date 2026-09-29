# Changelog

Format wg [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/); wersjonowanie wg [SemVer](https://semver.org/lang/pl/).

## [1.1.1] — 2026-09-29

### Bezpieczeństwo

- Odpowiedź na webhook z błędnym podpisem nie zawiera już `sign` (`{"status":"error"}`). Wcześniej zawierała podpis ważny dla wszystkich webhooków danego dnia. Zalecana aktualizacja.
- `sign` przesłany jako tablica jest odrzucany (`status: error`) zamiast kończyć się błędem 500.

## [1.1.0] — 2026-09-29

### Dodane

- Middleware `idosell.panel` (`EnsureIdosellLicense`): wpuszcza do panelu aplikacji z podpisanym linkiem i aktywną licencją, w przeciwnym razie 403.
- `Idosell::currentLicense()` oraz wstrzykiwanie `IdosellLicense` w kontrolerach tras panelu.
- `Idosell::panelUrl()` i helper `idosell_route()`: podpisane linki i akcje formularzy w panelu.

## [1.0.0]

### Dodane

- Webhooki `new-license`, `remove-license`, `launch` z weryfikacją `sign` i logowaniem (`idosell.verify-sign`, `idosell.log-webhook`).
- Model `IdosellLicense` z szyfrowanymi sekretami, fabryką i zakresami.
- Zdarzenia `LicenseActivated`, `LicenseDeactivating`, `LicenseDeactivated`, `AppLaunched`.
- Klient Admin API sprzedawcy (`X-API-KEY`, OAuth Bearer), zasoby `Snippets` i `OffersFeed`.
- Klient Apps API: `licenses()`, `setPrice()`, `post()`; automatyczne `installation/done`.
- Komendy `idosell:install`, `idosell:doctor`, `idosell:simulate`, `idosell:licenses`.
- Trait testowy `InteractsWithIdosell`.
- Baza wiedzy o IdoSell dla asystentów AI (`skills/`).
- Wsparcie Laravel 11, 12 i 13.
