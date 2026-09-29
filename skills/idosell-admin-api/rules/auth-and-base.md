# Autoryzacja i adres bazowy

Źródła: https://idosell.readme.io/docs/access-to-the-api,
spec sklepu: `https://{domain}/api/doc/admin/v8/json`

## Adres bazowy (host zależny od panelu)

```
https://{domain}/api/admin/v8
```

- **`{domain}` = domena panelu sprzedawcy** — różna dla każdego sklepu, np.
  `https://example-shop.iai-shop.com/api/admin/v8`.
- W aplikacji IdoSell Apps domena wynika z `api_url` licencji — nigdy nie hardkoduj jej w kodzie
  ani w configu aplikacji (SDK: `$license->adminApi()`).
- Wersja: `v8` (spec raportuje `8.9`). Wersję trzymaj w configu (`idosell.admin_api.version`).
- Model SaaS: system aktualizowany często, z naciskiem na wsteczną kompatybilność w ramach wersji.

## Uwierzytelnianie — dwie metody

> **W aplikacji IdoSell Apps klucz Admin API pochodzi z instalacji** — webhook `new_license`
> dostarcza zaszyfrowany `api_key` **per panel** (`idosell-apps/rules/signature-and-crypto.md`,
> zapis w modelu `IdosellLicense`). Wtedy **nie generujesz klucza ręcznie** i nie prosisz o niego
> sprzedawcy. Poniższe metody dotyczą integracji standalone, bez aplikacji IdoSell.

### A. Klucz API (`X-API-KEY`) — zalecane na start
- Wygeneruj w panelu: **Administration → API → Access keys to Admin API**.
- Nagłówek na każdym żądaniu:
  ```http
  X-API-KEY: <klucz>
  ```
- Każda aplikacja/integracja powinna mieć **własny** klucz.

### B. OAuth 2.0
- W panelu wybierz OAuth jako metodę autoryzacji.
- Pobierz token przez `POST /authorize/accessToken` (bramka AUTHORIZE), przekazując poświadczenia.
- Nagłówek:
  ```http
  Authorization: Bearer <token>
  ```
- Reference: https://idosell.readme.io/reference/authorizeaccesstokenpost

## Uprawnienia i ograniczenia klucza

Przy konfiguracji klucza ustaw:
- **Uprawnienia per zasób (gateways)**: `SYSTEM`, `CMS`, `CRM`, `OMS`, `PIM`, `WMS`. Nadawaj **minimum**
  potrzebne (np. odczyt produktów → PIM; snippety → CMS).
- **Whitelistę IP** — ogranicz dostęp do adresów backendu aplikacji.

## Schematy bezpieczeństwa w OpenAPI

Spec v8 definiuje:
- `ApiKeyAuth` → nagłówek `X-API-KEY`
- `bearerAuth` → HTTP Bearer (JWT)

## Format odpowiedzi i błędy

- Odpowiedzi JSON. Operacje wsadowe mogą zwracać `207` (multi-status) z tablicą `results` i błędami per element.
- Weryfikuj dokładne kody i pola błędów w spec sklepu (`/api/doc/admin/v8/json`) — nie zakładaj.

## Zasady
- Ustal wersję API i trzymaj się jej; śledź changelog (https://idosell.readme.io/docs/api-changelog).
- Nie wszystkie operacje panelu mają odpowiednik w API — brakujące zgłoś do wsparcia.
- Sekrety (klucz/token) tylko w `.env`/menedżerze sekretów.
