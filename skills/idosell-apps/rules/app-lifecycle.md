# Cykl życia aplikacji i licencji

Źródła: https://idosell.readme.io/docs/how-to-prepare-the-application,
https://idosell.readme.io/docs/turning-applications-on-and-off

## Typy aplikacji

| Typ | Opis | Konsekwencje |
|-----|------|--------------|
| **online** | Hostowana u dewelopera; sprzedawca korzysta z niej w panelu (zwykle iframe). | Publiczne endpointy https (webhooki + launch), szyfrowany `api_key`, obowiązkowe `installation/done`. |
| **downloadable** | Pobierana i uruchamiana po stronie użytkownika. | Uboższy payload (bez `api_key`), brak `installation/done`. |

## Konfiguracja w panelu dewelopera (zakładka *Technical data*)

| Pole | Adres przy SDK (domyślny prefiks) |
|------|-----------------------------------|
| How the application works | `Runs as an iframe` (online) |
| Link to working application | `{APP_URL}/api/idosell/webhooks/launch` |
| Webhook address to enable the application | `{APP_URL}/api/idosell/webhooks/new-license` |
| Webhook address to disable the application | `{APP_URL}/api/idosell/webhooks/remove-license` |
| Webhook address to modify application licence | `{APP_URL}/api/idosell/webhooks/new-license` |

Panel dopisuje `https://` sam. `php artisan idosell:install` wypisuje gotowe adresy. Tam też
deklarujesz uprawnienia klucza Admin API — do bramek, z których korzysta aplikacja.
Z panelu bierzesz: ID aplikacji, login dewelopera, **Application key** (sekret).

## Przepływ (online)

Wszystkie webhooki wysyła **serwer IdoSell** (server-to-server). Przeglądarka sprzedawcy dostaje
tylko adres z `redirect`; `sign` i `api_license` do niej nie trafiają.

```
1. Sprzedawca instaluje aplikację
   IdoSell ──POST new-license──▶ aplikacja   (api_url, zaszyfrowany api_key, api_license, sign)
2. Aplikacja: weryfikuje sign, sprawdza api_url, deszyfruje api_key, potwierdza licencję
   w application/license, zapisuje instalację
3. Aplikacja ──POST installation/done──▶ apps.idosell.com   (start trialu, aplikacja w menu)
4. Aplikacja odpowiada { status:"ok", sign }

5. Sprzedawca otwiera aplikację w panelu
   IdoSell ──POST launch──▶ aplikacja         (client_id, api_license, sign)
   aplikacja ◀── { status:"ok", redirect:<podpisany link>, sign }
   przeglądarka sprzedawcy otwiera redirect w iframe

6. Odinstalowanie
   IdoSell ──POST remove-license──▶ aplikacja  → sprzątanie, dezaktywacja, { status, sign }
```

W SDK kroki 2–4 to jeden webhook: `installation/done` jest wywoływane **w trakcie** webhooka,
licencja staje się aktywna dopiero po udanej finalizacji, a potem leci `LicenseActivated`.
Nieudana finalizacja → `status:"error"`, nowa licencja zostaje nieaktywna, istniejąca bez zmian.

Webhook **modyfikacji licencji** (np. zmiana wybranych sklepów) przychodzi na adres *new-license*
z tym samym kształtem — obsługa musi być idempotentna.

> Wyłączenie aplikacji ≠ blokada:
> https://idosell.readme.io/docs/what-is-the-difference-between-turning-off-an-app-and-blocking-it

## Payloady webhooków

Wszystkie to `POST` JSON z `sign`. Odpowiedź zawsze HTTP 200 z `{"status":"ok"|"error","sign":"…"}`;
przy błędnym `sign` — `{"status":"error"}` **bez** `sign`.

### new-license (instalacja i modyfikacja licencji)

```jsonc
{
  "client_id": 555001,                    // int — konto sprzedawcy
  "application_id": 1234,                 // int
  "api_url": "https://client1234.idosell.com/api/admin/",
  "api_license": "…",                     // numer licencji (sekret)
  "api_key": "…",                         // tylko online: ZASZYFROWANY AES-256-CBC
  "authorization_type": "key",            // tylko online: "key" | "OAuth"
  "contact_data": { "name": "…", "email": "…", "phone": "…" },   // opcj., dane osobowe
  "selected_shops": [ { "id": 1, "name": "…" } ],                // opcj.
  "sign": "…"
}
```

- `api_url` ma postać `https://clientNNNN.idosell.com/api/admin/` — bierz z niego **tylko host**
  (ścieżka bywa różna). Domeny paneli: `*.idosell.com`, `*.iai-shop.com`.
- Downloadable: bez `api_key` i `authorization_type`.

### installation/done (tylko online) — `POST apps.idosell.com/api/application/installation/done`

```json
{ "api_license": "…", "application_id": 1234, "developer": "…", "sign": "…" }
```

### launch — „Link to working application”

```json
{ "client_id": 555001, "application_id": 1234, "api_url": "…", "api_license": "…", "sign": "…" }
```
Odpowiedź (panel otwiera `redirect`):
```json
{ "status": "ok", "redirect": "https://twoja-aplikacja.example.com/panel?…&signature=…", "sign": "…" }
```

### remove-license

```json
{ "client_id": 555001, "application_id": 1234, "api_url": "…", "api_license": "…", "sign": "…" }
```

## Zasady

- **`sign` nie obejmuje treści ani `client_id`** — sam podpis nie dowodzi, którego sprzedawcy
  dotyczy webhook. Dlatego:
  - `launch` i `remove-license`: wymagaj `api_license` zgodnego z zapisaną licencją (porównanie
    w stałym czasie); `launch` tylko dla aktywnej licencji. `remove-license` bez zgodnej licencji →
    `ok` bez zmian.
  - `new-license`: potwierdź właściciela w `application/license` (`license[].number == api_license`,
    `license[].client.id == client_id`); ten sam `api_license` nie może należeć do innej instalacji;
    zmiana `api_url` istniejącej licencji tylko z nowym, innym `api_key`.
- **Idempotencja**: instalacja, modyfikacja i odinstalowanie mogą przyjść ponownie — bez duplikatów.
- **Odpowiadaj szybko**; ciężką pracę do kolejki (poza sprzątaniem przy odinstalowaniu).
- Przechowuj licencję per (`client_id`, `application_id`), sekrety zaszyfrowane — w SDK model
  `IdosellLicense` robi wszystko powyższe. Nie pisz tego od nowa (`rules/sdk-integration.md`).
- Nie filtruj webhooków po IP — IP nadawców nie są stałe i nie są publikowane.
