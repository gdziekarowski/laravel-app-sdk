# Cykl życia aplikacji i licencji

Źródła: https://idosell.readme.io/docs/how-to-prepare-the-application,
https://idosell.readme.io/docs/turning-applications-on-and-off

## Typy aplikacji

| Typ | Opis | Konsekwencje |
|-----|------|--------------|
| **online** | Hostowana na serwerze dewelopera; sprzedawca korzysta z niej zdalnie (zwykle osadzana w panelu). | Wymaga publicznych endpointów (webhooki + URL uruchomienia) i finalizacji instalacji. |
| **downloadable** | Pobierana i uruchamiana po stronie użytkownika. | Prostszy payload aktywacji; brak kroku `installation/done`. |

Konto dewelopera: https://apps.idosell.com (niezależne od kont sprzedawcy). Przy rejestracji aplikacji
konfigurujesz m.in.: **klucz aplikacji** (`applicationKey`), **login dewelopera**, **typ autoryzacji**
(`key` lub `OAuth`) oraz adresy webhooków: `url_webhook_new_license`, `url_webhook_remove_license`.

## Przepływ (online)

```
1. Sprzedawca włącza licencję w AppStore
        │  POST  → url_webhook_new_license          (payload z api_key/api_license, sign)
        ▼
2. Aplikacja: weryfikuje sign, deszyfruje api_key, zapisuje licencję/konfigurację
        │  odpowiada { status:"ok", sign }
        ▼
3. Aplikacja finalizuje instalację
        │  POST  → apps.idosell.com/api/application/installation/done
        ▼
4. Sprzedawca uruchamia aplikację w panelu
        │  POST  → URL aplikacji                    (payload z api_url, api_license, sign)
        ▼
   Aplikacja odpowiada { status:"ok", redirect:<url>, sign } → panel przekierowuje sprzedawcę
```

Deaktywacja: sprzedawca wyłącza licencję → POST na `url_webhook_remove_license` → aplikacja dezaktywuje
konto/konfigurację i odpowiada `{status, sign}`.

> Odróżnij **wyłączenie** aplikacji od **blokady** — patrz
> https://idosell.readme.io/docs/what-is-the-difference-between-turning-off-an-app-and-blocking-it

## Payloady webhooków

Wszystkie webhooki to `POST` z polem `sign` (weryfikacja: `rules/signature-and-crypto.md`).
Zawsze odpowiadaj JSON-em z własnym `sign`.

### Aktywacja licencji — `url_webhook_new_license`

**downloadable:**
```jsonc
{
  "client_id": 0,            // int — id konta sprzedawcy
  "application_id": 0,       // int
  "api_url": "https://{domain}/api",
  "api_license": "…",        // klucz licencji (sekret)
  "sign": "…",
  "contact_data": {          // opcjonalne
    "name": "…", "email": "…", "phone": "…"
  }
}
```

**online:**
```jsonc
{
  "client_id": 0,
  "application_id": 0,
  "api_url": "https://{domain}/api",
  "api_key": "…",                 // ZASZYFROWANY AES-256-CBC (patrz signature-and-crypto.md)
  "api_license": "…",
  "authorization_type": "key",    // "key" | "OAuth"
  "sign": "…",
  "contact_data": { },            // opcjonalne
  "selected_shops": [             // opcjonalne
    { "id": 0, "name": "…" }
  ]
}
```

**Oczekiwana odpowiedź aplikacji:**
```json
{ "status": "ok", "sign": "…" }
```
(`status: "error"` przy niepowodzeniu.)

### Finalizacja instalacji (tylko online) — `POST apps.idosell.com/api/application/installation/done`

```json
{
  "api_license": "…",
  "application_id": 0,
  "developer": "…",
  "sign": "…"
}
```

### Uruchomienie w panelu sprzedawcy — POST na URL aplikacji

Żądanie:
```json
{
  "client_id": 0,
  "application_id": 0,
  "api_url": "https://{domain}/api",
  "api_license": "…",
  "sign": "…"
}
```
Odpowiedź (panel wykona przekierowanie pod `redirect`):
```json
{ "status": "ok", "redirect": "https://twoja-aplikacja/…", "sign": "…" }
```

### Deaktywacja licencji — `url_webhook_remove_license`

```json
{
  "client_id": 0,
  "application_id": 0,
  "api_url": "https://{domain}/api",
  "api_license": "…",
  "sign": "…"
}
```

## Zasady

- **Idempotencja**: aktywacja/deaktywacja może przyjść ponownie — obsłuż powtórki bez duplikowania danych.
- **Najpierw sign, potem logika**: odrzucaj żądania z niepoprawnym podpisem (HTTP 200 z `status:"error"`
  lub błąd — zgodnie z zachowaniem platformy; loguj bez sekretów).
- Przechowuj `api_license`/`api_url`/uprawnienia per `client_id` — w SDK robi to model
  `IdosellLicense` (klucz: `client_id` + `application_id`, sekrety szyfrowane).
- Szczegóły instalacji, pola i ewentualne zmiany zweryfikuj u źródła (docs + `llms.txt`) — nie wymyślaj pól.
