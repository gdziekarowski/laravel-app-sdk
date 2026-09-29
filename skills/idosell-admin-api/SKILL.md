---
name: idosell-admin-api
description: "Użyj tego skilla przy korzystaniu z IdoSell Admin API sklepu (REST, /api/admin/v8) — API konkretnego panelu sprzedawcy. Aktywuj gdy pracujesz nad: autoryzacją Admin API (nagłówek X-API-KEY lub OAuth2 Bearer), bazowym URL zależnym od panelu (https://{domain}/api/admin/v8), wstrzykiwaniem kodu do sklepu przez snippety (snippets/campaign, snippets/snippets, zone head/body), odczytem produktów (products/products/search, products/products), feedem produktowym / eksportem ofert (partners/v2 offers/feed, IOF30, XSLT). Triggery: 'IdoSell Admin API', 'api/admin/v8', 'X-API-KEY', 'iai-shop.com', 'snippet', 'pixel w sklepie', 'products/search', 'feed produktowy', 'eksport ofert'. NIE mylić z idosell-apps (platforma aplikacji). Obejmuje użycie przez paczkę idosell/laravel-app-sdk."
license: MIT
metadata:
  author: iaisa
  source: https://idosell.readme.io/docs/access-to-the-api
---

# IdoSell Admin API — użycie (v8)

Praktyczna referencja korzystania z **Admin API sklepu IdoSell** (REST, `/api/admin/v8`). To API
**konkretnego panelu sprzedawcy** — host zależy od panelu, a w aplikacji IdoSell Apps wynika
z `api_url` licencji, więc jest inny dla każdej instalacji.

> To NIE jest to samo co skill `idosell-apps` (platforma aplikacji: licencje, webhooki cyklu życia).
> Tu chodzi o zwykłe REST API danego sklepu.

## Dwa najczęstsze zastosowania w aplikacjach

1. **Wstrzyknięcie własnego kodu na strony sklepu** → mechanizm **snippetów** (`snippets/*`); to tędy
   osadza się pixele i skrypty zewnętrznych systemów. Zob. `rules/snippets-pixel.md`.
2. **Feed produktowy** → `partners/v2 offers/feed` (ten sam host + `X-API-KEY`). IdoSell zakłada,
   hostuje i odświeża feed (IOF30 + sterownik XSLT) oraz zwraca stały URL. Zob. `rules/products-and-feed.md`.
   Odczyt produktów nie jest do tego potrzebny — nie buduj własnego generatora XML.

## Consistency First

Zanim dodasz kod, sprawdź, co daje `idosell/laravel-app-sdk` (nie duplikuj):

- Klient z autoryzacją, timeoutami i retry: `$license->adminApi()`.
- Snippety i feedy z gotową idempotencją: `Idosell::snippets($license)`, `Idosell::offersFeed($license)`.
- Host wyprowadzany z `api_url` licencji — nigdy nie hardkoduj domeny sprzedawcy.
- Wzorce backendu: skill `laravel-best-practices` (Service/Action, HTTP client, paginacja).

## Bezpieczeństwo i RODO (obowiązkowe)

- `X-API-KEY` / token OAuth → **sekret**. `.env` / menedżer sekretów; nie commituj, nie loguj.
- Admin API zwraca **dane sprzedawcy i klientów** (zamówienia, kontakty) — poufne (RODO). Pobieraj tylko
  to, co potrzebne (minimalne uprawnienia klucza); nie loguj danych osobowych.
- Klucz konfiguruj z whitelistą IP i minimalnym zestawem uprawnień (gateways).

## Quick Reference

### 1. Autoryzacja i adres bazowy → `rules/auth-and-base.md`

- Base URL: `https://{domain}/api/admin/v8` — **`{domain}` = domena panelu sprzedawcy**.
- Auth: `X-API-KEY: <klucz>` (panel: *Administration → API → Access keys to Admin API*) albo OAuth2 `Authorization: Bearer <token>`.
- Wersja bieżąca: v8. Uprawnienia per gateway (SYSTEM/CMS/CRM/OMS/PIM/WMS).
- Spec OpenAPI danego sklepu: `https://{domain}/api/doc/admin/v8/json`.

### 2. Snippety / wstrzykiwanie kodu → `rules/snippets-pixel.md`

- `POST /snippets/campaign` — utwórz kampanię (grupa); `GET/PUT/DELETE` do zarządzania.
- `POST /snippets/snippets` — utwórz snippet: `type` (`html`/`javascript`/`cgi`), `zone` (`head`/`bodyBegin`/`bodyEnd`), `body[].body` = kod, `pages.all`, `campaign` (wymagane).
- `display` (widoczność na urządzeniach) ustawiaj jawnie; SDK robi to w `upsert()`.

### 3. Produkty i feed → `rules/products-and-feed.md`

- Feed ofertowy: `partners/v2 offers/feed` — `POST` (create), `PUT` (update/`active`), `GET` (`?shop=`/`?id=`),
  `DELETE` (`?id=`). IdoSell hostuje plik i zwraca URL; payload to IOF30 + XSLT `driver`.
- Odczyt produktów (nie do feedu): `POST /products/products/search` (paginacja ≤100), `GET /products/products`.
- Powiązane: `POST /products/synchronization/file` (import IOF 3.0).

### 4. Wywołania przez SDK → `rules/sdk-integration.md`

- Klient z licencji, helpery ścieżek bramek, idempotentne snippety i feedy, obsługa błędów przy HTTP 200.

## How to Apply

1. Ustal zadanie i przeczytaj regułę: auth/URL → §1; snippety → §2; produkty/feed → §3; kod → §4.
2. **Weryfikuj dokładne pola u źródła** — nie zgaduj:
   - Spec sklepu: `https://{domain}/api/doc/admin/v8/json`
   - Reference: https://idosell.readme.io/reference, dostęp: https://idosell.readme.io/docs/access-to-the-api
   - Indeks: https://idosell.readme.io/llms.txt
