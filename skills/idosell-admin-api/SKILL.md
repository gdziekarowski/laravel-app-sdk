---
name: idosell-admin-api
description: "Użyj przy wywołaniach IdoSell Admin API sklepu (REST /api/admin/v8) i API partnerskiego (/api/partners/v2) — API konkretnego panelu sprzedawcy — także przez $license->adminApi() z paczki idosell/laravel-app-sdk. Obejmuje: adres bazowy z api_url licencji, autoryzację (X-API-KEY, OAuth Bearer), uprawnienia bramek, wstrzykiwanie kodu/pixeli przez snippety (snippets/campaign, snippets/snippets, Idosell::snippets), feed produktowy (partners/v2 offers/feed, IOF30, XSLT, Idosell::offersFeed), odczyt produktów (products/products/search), obsługę błędów (ApiException, 207, faultString). Triggery: 'Admin API', 'api/admin/v8', 'X-API-KEY', 'snippet', 'pixel w sklepie', 'feed produktowy', 'offers/feed', 'products/search', 'adminApi()'. Webhooki licencji, sign, panel aplikacji i Apps API → skill idosell-apps."
license: MIT
metadata:
  author: iaisa
  source: https://idosell.readme.io/docs/access-to-the-api
---

# IdoSell Admin API — użycie (v8)

Referencja **Admin API sklepu IdoSell** (REST, `/api/admin/v8`) i API partnerskiego
(`/api/partners/v2`). To API **konkretnego panelu sprzedawcy** — w aplikacji IdoSell Apps host
wynika z `api_url` licencji (`https://clientNNNN.idosell.com/api/admin/`), więc jest inny dla każdej
instalacji.

> Licencje, webhooki cyklu życia, `sign`, panel aplikacji, Apps API → skill `idosell-apps`.

## Dwa najczęstsze zastosowania w aplikacjach

1. **Własny kod na stronach sklepu** (pixel, skrypt) → **snippety** (`snippets/*`).
   Zob. `rules/snippets-pixel.md`.
2. **Feed produktowy** → `partners/v2 offers/feed` (ten sam host i klucz). IdoSell
   generuje, hostuje i odświeża plik (IOF30 + XSLT) i zwraca stały URL. Nie buduj własnego
   generatora XML. Zob. `rules/products-and-feed.md`.

## Consistency First

W aplikacji z `idosell/laravel-app-sdk` nie pisz własnego klienta HTTP:

- Klient z autoryzacją, timeoutami, retry i ochroną klucza: `$license->adminApi()`.
- Snippety i feedy z idempotencją: `Idosell::snippets($license)`, `Idosell::offersFeed($license)`.
- Host zawsze z `api_url` licencji — nigdy nie hardkoduj domeny sprzedawcy.

Szczegóły i reguły „rób / nie rób”: `rules/sdk-integration.md`.

## Bezpieczeństwo i RODO (obowiązkowe)

- Klucz Admin API / token OAuth to **sekret** sprzedawcy — nie loguj, nie wysyłaj nigdzie poza jego
  panel IdoSell (SDK pilnuje listy domen i blokuje przekierowania).
- Admin API zwraca **dane sprzedawcy i klientów** (zamówienia, kontakty) — poufne (RODO). Pobieraj
  tylko potrzebne pola, deklaruj minimum bramek, nie loguj odpowiedzi.
- Dane wstawiane do kodu snippetu zabezpieczaj przed XSS (`e()`, `json_encode()`).

## Quick Reference

### 1. Autoryzacja i adres bazowy → `rules/auth-and-base.md`

- Base URL: `https://{domena-panelu}/api/admin/v8`; spec sklepu: `https://{domena-panelu}/api/doc/admin/v8/json`.
- Auth: `X-API-KEY: <klucz>` albo `Authorization: Bearer <token>` (OAuth).
- W aplikacji IdoSell Apps klucz przychodzi z webhooka instalacji; bramki deklarujesz w panelu dewelopera.

### 2. Snippety / wstrzykiwanie kodu → `rules/snippets-pixel.md`

- `snippets/campaign` (kampania = grupa, opcjonalnie per sklep) → `snippets/snippets` (`campaign` wymagane).
- Pola: `type` (`html`/`javascript`/`cgi`), `zone` (`head`/`bodyBegin`/`bodyEnd`), `body[].body`,
  `pages.all`, **`display` ustawiaj jawnie**, `dateEnd` (wyłączenie w danym dniu).

### 3. Produkty i feed → `rules/products-and-feed.md`

- Feed: `partners/v2 offers/feed` — `POST` / `PUT` / `GET ?shop=|?id=` / `DELETE ?id=`.
- Odczyt produktów (nie do feedu): `POST products/products/search` (≤100/stronę), `GET products/products`.

### 4. Wywołania przez SDK → `rules/sdk-integration.md`

- `adminApi()`, `admin()`/`partners()`, snippety, feed, wyjątki, błędy przy HTTP 200.

## How to Apply

1. Ustal zadanie: auth/URL → §1; snippety → §2; produkty/feed → §3; **kod w Laravelu → zawsze §4**.
2. **Weryfikuj pola u źródła** — nie zgaduj:
   - Spec sklepu: `https://{domena-panelu}/api/doc/admin/v8/json`
   - Reference: https://idosell.readme.io/reference, indeks: https://idosell.readme.io/llms.txt
