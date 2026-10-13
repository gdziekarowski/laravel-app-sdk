# Autoryzacja i adres bazowy

Źródła: https://idosell.readme.io/docs/access-to-the-api,
spec sklepu: `https://{domena-panelu}/api/doc/admin/v8/json`

## Adres bazowy (host zależny od panelu)

```
https://{domena-panelu}/api/admin/v8        Admin API
https://{domena-panelu}/api/partners/v2     API partnerskie (feed) — ten sam host i klucz
```

- **`{domena-panelu}` jest różna dla każdego sklepu**, np. `client1234.idosell.com` albo
  `example-shop.iai-shop.com`.
- W aplikacji IdoSell Apps domena wynika z `api_url` licencji (`https://clientNNNN.idosell.com/api/admin/`)
  — bierz z niego **tylko host**, ścieżkę buduj sam. Nigdy nie hardkoduj domeny (SDK: `$license->adminApi()`).
- Klucz wysyłaj wyłącznie po **https** na domeny paneli IdoSell (`*.idosell.com`, `*.iai-shop.com`),
  nigdy na IP, `localhost` ani domeny wewnętrzne; nie śledź przekierowań (zabrałyby nagłówek
  z kluczem na inny host).
- Wersja: `v8`. Trzymaj ją w configu (`IDOSELL_ADMIN_API_VERSION`), nie w ścieżkach w kodzie.
- Model SaaS: system aktualizowany często, z naciskiem na wsteczną kompatybilność w ramach wersji;
  śledź changelog (https://idosell.readme.io/docs/api-changelog).

## Uwierzytelnianie

> **W aplikacji IdoSell Apps klucz pochodzi z instalacji** — webhook `new-license` dostarcza
> zaszyfrowany `api_key` per panel (skill `idosell-apps`), SDK zapisuje go w `IdosellLicense`.
> Nie generujesz klucza ręcznie i nie prosisz o niego sprzedawcy. Ręczne klucze poniżej dotyczą
> integracji standalone.

### A. Klucz API (`X-API-KEY`)
- Standalone: panel sklepu → **Administration → API → Access keys to Admin API**; każda integracja
  ma **własny** klucz.
- Nagłówek: `X-API-KEY: <klucz>`.

### B. OAuth 2.0
- Token: `POST /authorize/accessToken` (bramka AUTHORIZE) — https://idosell.readme.io/reference/authorizeaccesstokenpost
- Nagłówek: `Authorization: Bearer <token>`.
- W SDK: licencja z `authorization_type = "OAuth"` trzyma token w polu `api_key`; pobranie
  i odświeżanie tokenu jest po stronie aplikacji.

Schematy w OpenAPI v8: `ApiKeyAuth` (nagłówek `X-API-KEY`), `bearerAuth` (HTTP Bearer).

## Uprawnienia (bramki)

- Każda bramka API ma swoje uprawnienie. Nadawaj **minimum** — w aplikacji IdoSell Apps deklarujesz
  w panelu dewelopera tylko bramki, z których korzysta aplikacja; 401/403 zwykle znaczy brak uprawnienia.
- Zmiana uprawnień klucza: https://idosell.readme.io/docs/how-do-i-change-api-key-permissions
- Standalone: klucz można dodatkowo ograniczyć whitelistą IP serwera wywołującego (dotyczy
  wywołań *do* Admin API — nie ma związku z webhookami IdoSell, których po IP się nie filtruje).

## Format odpowiedzi i błędy

- JSON. Operacje wsadowe mogą zwracać `207` (multi-status) z `results[]` i błędami per element —
  HTTP 2xx nie oznacza sukcesu, sprawdzaj `results[].errors`.
- API partnerskie zgłasza błąd w treści: `{"errors":{"faultCode","faultString"}}`.
- Dokładne kody i pola błędów potwierdzaj w spec sklepu — nie zakładaj.

## Zasady
- Ustal wersję API i trzymaj się jej.
- Nie wszystkie operacje panelu mają odpowiednik w API — wykonaj akcję ręcznie, znajdź endpoint,
  przetestuj na panelu demo; braki zgłoś do wsparcia (https://idosell.readme.io/docs/support).
- Sekrety (klucz/token) tylko w bazie zaszyfrowane albo w `.env` / menedżerze sekretów.
