# Dostęp do API i autoryzacja

Źródła: https://idosell.readme.io/docs/getting-started, https://idosell.readme.io/docs/access-to-the-api,
https://idosell.readme.io/docs/api-endpoints

Rozróżniaj dwa API:

1. **Admin API sprzedawcy** — REST API konkretnego sklepu IdoSell; tu robisz operacje biznesowe
   (zamówienia, produkty, klienci).
2. **Apps API (deweloperskie)** — `apps.idosell.com/api/*`; tu zarządzasz licencjami i rozliczeniami aplikacji.

---

## 1. Admin API sprzedawcy

- Model **SaaS**: wszyscy sprzedawcy na tej samej wersji systemu; różnią się danymi i konfiguracją.
  Zmiany/patche wdrażane niemal codziennie, nowe funkcje na wszystkich kontach w ≤ 2 tygodnie.
- **Base URL**: `https://{domain}/api/...` (domena = numer panelu + stała część, np.
  `example-shop.iai-shop.com`).
- **Spec OpenAPI danego sklepu**: `https://{domain}/api/doc/admin/v{X}/json` — zawsze sprawdzaj tam
  aktualne ścieżki/pola dla właściwej wersji `v{X}`.
- **Środowiska**: produkcja (per sprzedawca) + **Sandbox Demo** do testów przed wdrożeniem.
- **Wersjonowanie**: API jest wersjonowane z naciskiem na wsteczną kompatybilność — patrz
  https://idosell.readme.io/docs/versioning-backward-compatibility. Ustal wersję i trzymaj się jej.

### Autoryzacja — dwie metody

**A. Klucz API (`X-API-KEY`)**
- Generowany w panelu sprzedawcy: *Administration → API → Access keys to Admin API*.
- Przekazywany w nagłówku:
  ```http
  X-API-KEY: {klucz}
  ```
- Każda aplikacja powinna mieć **własny** klucz.

**B. OAuth 2.0**
- W *Administration → API → Access keys* wybierz OAuth.
- Pobierz token przez bramkę **AUTHORIZE**: `POST /authorize/accessToken` (z poświadczeniami).
- Token w nagłówku:
  ```http
  Authorization: Bearer {token}
  ```
- SDK wysyła token jako `Authorization: Bearer`, gdy licencja ma `authorization_type = "OAuth"`
  (token trzymany w polu `api_key`). Samo pobieranie i odświeżanie tokenu jest po stronie aplikacji —
  rozważ gotowy klient, np. `steverhoades/oauth2-openid-connect-client`.

### Uprawnienia (gateways)

Zakres klucza/tokenu ogranicza dostęp do obszarów API:

| Gateway | Obszar |
|---------|--------|
| `SYSTEM` | ustawienia/systemowe |
| `CMS` | treści, klienci (odczyt klientów) |
| `CRM` | relacje z klientami |
| `OMS` | zamówienia, zwroty, RMA |
| `PIM` | produkty/katalog |
| `WMS` | magazyn |

Nadawaj klientowi **minimum** potrzebnych uprawnień. Zmiana uprawnień klucza:
https://idosell.readme.io/docs/how-do-i-change-api-key-permissions

> Nie wszystkie operacje panelu mają odpowiednik w API. Metoda pracy: wykonaj akcję ręcznie w panelu,
> znajdź odpowiadający endpoint, przetestuj w Sandboxie. Braki zgłoś do wsparcia
> (https://idosell.readme.io/docs/support).

---

## 2. Apps API (deweloperskie)

- **Base URL**: `https://apps.idosell.com/api/`
- Każde żądanie wymaga podpisu `sign` (patrz `rules/signature-and-crypto.md`).
- Odpowiedzi zawierają `status` (`"ok"|"error"` lub `"success"|"error"`) i opcjonalnie `errors[]`.

| Endpoint | Metoda | Cel | Kluczowe pola żądania |
|----------|--------|-----|-----------------------|
| `/application/license` | POST | Lista licencji aplikacji | `application_id`, `developer`, `sign`, opcj. `api_license`, `active` |
| `/application/license/setPrice` | POST | Ustaw opłatę (model usage/limit) | `application_id`, `developer`, `api_license`, `price`, `sign` |
| `/application/installation/done` | POST | Potwierdź instalację (online) | `api_license`, `application_id`, `developer`, `sign` |

Odpowiedź `/application/license` zawiera m.in.: status, listę licencji z danymi klienta, modelem
cenowym, uprawnieniami API oraz statusem instalacji.

Semantyka `setPrice` i limity — patrz `rules/payments-and-verification.md`.

---

## Weryfikacja u źródła

Pełne specyfikacje (pola, wersje, uprawnienia) potwierdzaj w:
- https://idosell.readme.io/reference (API Reference)
- https://idosell.readme.io/llms.txt (indeks + OpenAPI)
- `https://{domain}/api/doc/admin/v{X}/json` (spec danego sklepu)

Nie zakładaj pól, których nie widzisz w dokumentacji.
