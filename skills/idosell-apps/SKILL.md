---
name: idosell-apps
description: "Użyj przy budowie, integracji, testowaniu lub publikacji aplikacji dla platformy IdoSell Apps (apps.idosell.com) — szczególnie w Laravelu z paczką idosell/laravel-app-sdk. Obejmuje: webhooki cyklu życia licencji (new-license, launch, remove-license, modyfikacja licencji), podpis sign, deszyfrację api_key, panel aplikacji w iframe (middleware idosell.panel, idosell_route), zdarzenia SDK (LicenseActivated, LicenseDeactivating, AppLaunched), Apps API (application/license, installation/done, setPrice), webhooki zdarzeń sklepu (orderCreated, productStockUpdated…), modele płatności i weryfikację aplikacji. Triggery: 'IdoSell Apps', 'aplikacja IdoSell', 'webhook licencji', 'installation/done', 'setPrice', 'panel aplikacji IdoSell', 'idosell:simulate', 'laravel-app-sdk'. Do wywołań REST Admin API sklepu (produkty, snippety, feed) użyj skilla idosell-admin-api."
license: MIT
metadata:
  author: IdoSell (IAI S.A.)
  source: https://idosell.readme.io/docs/apps
---

# IdoSell Apps — przygotowanie aplikacji

Referencja budowy aplikacji dla platformy **IdoSell Apps** (https://apps.idosell.com): cykl życia
licencji, podpisy, kryptografia, Apps API, webhooki, płatności, weryfikacja — oraz jak zrobić to
paczką `idosell/laravel-app-sdk`.

Platforma zajmuje się promocją aplikacji w panelu sprzedawcy, subskrypcjami i rozliczeniami; deweloper
dostarcza funkcjonalność i (dla typu *online*) hosting. Konto dewelopera na https://apps.idosell.com
jest niezależne od kont sprzedawcy IdoSell.

## Consistency First

Zanim napiszesz cokolwiek nowego, sprawdź, czy SDK już tego nie robi:

- Webhooki cyklu życia, weryfikacja `sign`, deszyfracja `api_key`, zapis licencji z szyfrowanymi
  sekretami, potwierdzenie `installation/done`, panel w iframe, klienci Apps/Admin API, snippety,
  feed, redakcja logów — **wszystko jest w paczce**.
- Własną logikę wpinaj **listenerami zdarzeń** (`LicenseActivated`, `LicenseDeactivating`,
  `LicenseDeactivated`, `AppLaunched`), a nie własnym kontrolerem webhooków.
- Admin API wołaj przez `$license->adminApi()` / `Idosell::snippets($license)` /
  `Idosell::offersFeed($license)` — nie przez własny klient HTTP.

Szczegóły i reguły „rób / nie rób”: `rules/sdk-integration.md`.

## Bezpieczeństwo i RODO (obowiązkowe)

- `IDOSELL_APPLICATION_KEY`, `api_key`, `api_license`, tokeny OAuth, `sign` i podpisane linki panelu
  to **sekrety** — nie commituj, nie loguj, nie odsyłaj w odpowiedziach. Klucz aplikacji tylko
  w `.env` / menedżerze sekretów.
- `contact_data` i dane z Admin API (zamówienia, klienci) to **dane osobowe** (RODO). Nie loguj
  payloadów webhooków ani zdarzeń. W przykładach wartości syntetyczne (`client_id` 555001,
  `clientxxxx.idosell.com`, `jan.kowalski@example.com`).
- Wszystkie webhooki wysyła **serwer** IdoSell; adresy serwerów IdoSell mogą się zmieniać — **nie filtruj po IP**.

## Quick Reference

### 1. Cykl życia aplikacji i licencji → `rules/app-lifecycle.md`

- Typy: **online** (hostowana u dewelopera, zwykle w iframe panelu) i **downloadable**.
- Instalacja / modyfikacja licencji → POST na adres *new-license*; odinstalowanie → *remove-license*;
  otwarcie w panelu → POST na *Link to working application* (launch), odpowiedź `{status, redirect, sign}`.
- Online: aplikacja wywołuje `POST apps.idosell.com/api/application/installation/done` — dopiero
  wtedy startuje trial i aplikacja pojawia się w menu panelu. SDK robi to w trakcie webhooka.
- Zawsze HTTP 200 z `{status:"ok"|"error", sign}`; przy błędnym podpisie `{status:"error"}` **bez** `sign`.

### 2. Podpis i kryptografia → `rules/signature-and-crypto.md`

- `sign = sha256(developer|Y-m-d|application_key)` — podpis dzienny aplikacji; instalację identyfikuje
  `api_license`. SDK: `Idosell::verify()` (±1 dzień), `Idosell::sign()`.
- `api_key` (online, `authorization_type=key`): AES-256-CBC kluczem aplikacji, IV z
  `https://apps.idosell.com/keyset`. SDK: `Idosell::decryptApiKey()`.

### 3. API i autoryzacja → `rules/api-and-auth.md`

- **Apps API** `https://apps.idosell.com/api/`: `application/license`, `application/license/setPrice`,
  `application/installation/done`; każde żądanie z `sign`. SDK: `Idosell::apps()`.
- **Admin API sklepu**: host z `api_url` licencji (`https://clientNNNN.idosell.com/api/admin/`),
  klucz z webhooka instalacji. Szczegóły REST → skill `idosell-admin-api`.

### 4. Webhooki zdarzeń sklepu → `rules/webhooks.md`

- Osobny mechanizm od webhooków licencji: `orderCreated`, `productStockUpdated`, `clientCreated`,
  `returnCreated`, `rmaCreated`… Format = bramka Admin API w wybranej wersji. SDK go nie obsługuje.

### 5. Płatności i weryfikacja → `rules/payments-and-verification.md`

- **Fixed price** albo **Usage/Limit** (`setPrice` **nadpisuje** kwotę okresu, nie dolicza; nie
  przekraczaj limitu sprzedawcy).
- Weryfikacja: kontrola formalna (PL+EN, ikona, zdjęcia, cena, trial) + techniczna; app tester.

### 6. Implementacja w Laravelu → `rules/sdk-integration.md`

- Co daje SDK, panel aplikacji, listenery, Admin/Apps API, testy, `idosell:simulate`, produkcja.

## How to Apply

1. Ustal zadanie i przeczytaj regułę: cykl życia → §1; podpis/crypto → §2; Apps API → §3;
   zdarzenia sklepu → §4; rozliczenia/publikacja → §5; **kod w Laravelu → zawsze §6**.
2. Sprawdzaj sygnatury w kodzie paczki (`vendor/idosell/laravel-app-sdk/src`) i dokumentację
   paczki (`vendor/idosell/laravel-app-sdk/docs`), zanim użyjesz metody.
3. **Pól API nie zgaduj** — weryfikuj u źródła:
   - https://idosell.readme.io/docs/apps (i podstrony), indeks: https://idosell.readme.io/llms.txt
   - Admin API danego sklepu: `https://{domena-panelu}/api/doc/admin/v8/json`
