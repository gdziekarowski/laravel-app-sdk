---
name: idosell-apps
description: "Użyj tego skilla przy tworzeniu, integracji lub publikacji aplikacji dla platformy IdoSell Apps (apps.idosell.com). Aktywuj gdy pracujesz nad: cyklem życia licencji i webhookami aplikacji (url_webhook_new_license, url_webhook_remove_license, uruchamianie w panelu sprzedawcy), generowaniem i weryfikacją podpisu sign (SHA-256), deszyfracją api_key (AES-256-CBC), dostępem do IdoSell Admin API (X-API-KEY lub OAuth2, uprawnienia SYSTEM/CMS/CRM/OMS/PIM/WMS), endpointami apps.idosell.com/api/* (application/license, setPrice, installation/done), webhookami zdarzeń Admin API (orderCreated, productStockUpdated, ...), modelami płatności i procesem weryfikacji aplikacji. Triggery: 'IdoSell Apps', 'aplikacja IdoSell', 'webhook licencji', 'przygotowanie aplikacji', 'apps.idosell.com', 'Admin API', 'idosell'. Obejmuje implementację w Laravelu przez paczkę idosell/laravel-app-sdk."
license: MIT
metadata:
  author: iaisa
  source: https://idosell.readme.io/docs/apps
---

# IdoSell Apps — przygotowanie aplikacji

Praktyczna referencja budowy aplikacji dla platformy **IdoSell Apps** (https://apps.idosell.com):
cykl życia licencji, podpisy, kryptografia, Admin API, webhooki, płatności, weryfikacja — oraz jak
zmapować to na paczkę `idosell/laravel-app-sdk`.

Platforma zajmuje się promocją aplikacji w panelu sprzedawcy, subskrypcjami i rozliczeniami; deweloper
dostarcza funkcjonalność i (dla typu *online*) hosting. Konto dewelopera zakłada się na
https://apps.idosell.com — jest niezależne od kont sprzedawcy IdoSell.

## Consistency First

Zanim napiszesz cokolwiek nowego, sprawdź, czy SDK już tego nie robi — to najczęstsze źródło
zdublowanej i gorszej implementacji:

- Podpis `sign`, deszyfracja `api_key`, klienci Apps/Admin API, webhooki cyklu życia, model licencji
  z szyfrowanymi sekretami, logowanie z redakcją danych — **wszystko jest w paczce**.
- Własną logikę wpinaj **zdarzeniami** (`LicenseActivated`, `LicenseDeactivating`, `AppLaunched`),
  a nie przez podmianę kontrolera.
- Wzorce backendu: skill `laravel-best-practices` (Form Requests, Action/Service, HTTP client
  z `timeout` + `retry`).

Szczegóły w `rules/sdk-integration.md`.

## Bezpieczeństwo i RODO (obowiązkowe)

- `api_key`, `api_license`, `applicationKey`, tokeny OAuth i `sign` to **sekrety** — nigdy nie commituj,
  nie loguj w pełnej postaci i nie umieszczaj w odpowiedziach. Trzymaj w `.env` / menedżerze sekretów.
- Dane sprzedawców i klientów końcowych z Admin API są **poufne** (RODO). W przykładach używaj wartości
  syntetycznych (`jan.kowalski@example.com`, `123 456 789`).
- Zawsze weryfikuj `sign` przychodzących webhooków, zanim zaufasz payloadowi.

## Quick Reference

### 1. Cykl życia aplikacji i licencji → `rules/app-lifecycle.md`

- Dwa typy: **online** (hostowana u dewelopera) i **downloadable** (uruchamiana lokalnie).
- Aktywacja licencji → POST na `url_webhook_new_license`; deaktywacja → `url_webhook_remove_license`.
- Uruchomienie w panelu sprzedawcy → POST na URL aplikacji; odpowiedz `{status, redirect, sign}`.
- Finalizacja instalacji (online) → POST `apps.idosell.com/api/application/installation/done`.
- Każdy webhook zawiera `sign`; odpowiadaj `{status:"ok"|"error", sign}`.

### 2. Podpis i kryptografia → `rules/signature-and-crypto.md`

- `sign = hash('sha256', $login.'|'.date('Y-m-d').'|'.$applicationKey)`.
- Deszyfracja `api_key` (authorization_type="key"): AES-256-CBC, IV z `apps.idosell.com/keyset`.
- Weryfikuj każdy przychodzący `sign`; przy niezgodności odrzuć request.

### 3. Dostęp do API i autoryzacja → `rules/api-and-auth.md`

- **Admin API sprzedawcy**: `https://{domain}/api/...`; OpenAPI: `https://{domain}/api/doc/admin/v{X}/json`.
- Autoryzacja: **API key** (`X-API-KEY`) albo **OAuth2** (`/authorize/accessToken` → `Authorization: Bearer`).
- Uprawnienia (gateways): `SYSTEM`, `CMS`, `CRM`, `OMS`, `PIM`, `WMS`.
- **Apps API**: `https://apps.idosell.com/api/` — `application/license`, `application/license/setPrice`,
  `application/installation/done`. Zawsze z `sign`.

### 4. Webhooki zdarzeń (Admin API) → `rules/webhooks.md`

- Konfiguracja per-sklep w panelu; wybór wersji API decyduje o formacie danych.
- Eventy m.in.: `orderCreated`, `orderPaid`, `orderStatusUpdated`, `productStockUpdated`,
  `clientCreated`, `returnCreated`, `rmaCreated` (pełna lista w regule).
- Payload zgodny z odpowiednią bramką Admin API; uprawnienia: klienci→CMS, produkty→PIM, zamówienia/zwroty/RMA→OMS.

### 5. Płatności i weryfikacja → `rules/payments-and-verification.md`

- Modele: **Fixed price** (stała opłata cykliczna) i **Usage/Limit** (elastyczny, przez `setPrice`).
- `setPrice` **nadpisuje** wartość (nie kumuluje); nie przekraczaj limitu wydatków sprzedawcy.
- Weryfikacja: kontrola formalna (opis PL+EN, ikona, zdjęcia, cena, trial) + techniczna (Admin API, webhooki).
- Testowanie: wbudowany **app tester** w panelu dewelopera (przy zgłoszeniu do publikacji).

### 6. Implementacja w Laravelu → `rules/sdk-integration.md`

- Co daje `idosell/laravel-app-sdk`, czego nie pisać od nowa, gdzie wpinać własną logikę,
  jak testować i jak symulować webhooki lokalnie.

## How to Apply

1. Ustal, nad czym pracujesz, i przeczytaj właściwe reguły:
   instalacja/uruchomienie aplikacji → §1; podpis/crypto → §2; wywołania API → §3; nasłuch zdarzeń → §4;
   rozliczenia/publikacja → §5; kod → §6.
2. Trzymaj się `rules/sdk-integration.md` i skilla `laravel-best-practices`; sprawdzaj sibling files
   pod istniejące wzorce (Consistency First).
3. **Zawsze weryfikuj szczegóły API u źródła** — nie wymyślaj pól. Aktualne specyfikacje:
   - Dokumentacja: https://idosell.readme.io/docs/apps (i podstrony).
   - Maszynowy indeks + OpenAPI: https://idosell.readme.io/llms.txt oraz `/reference`.
   - Admin API danego sklepu: `https://{domain}/api/doc/admin/v{X}/json`.
