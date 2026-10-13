# Dostęp do API i autoryzacja

Źródła: https://idosell.readme.io/docs/getting-started, https://idosell.readme.io/docs/access-to-the-api,
https://idosell.readme.io/docs/api-endpoints

Aplikacja korzysta z dwóch API:

| API | Do czego | Adres | Autoryzacja |
|-----|----------|-------|-------------|
| **Apps API** (deweloperskie) | licencje i opłaty Twojej aplikacji | `https://apps.idosell.com/api/` | pole `sign` w treści |
| **Admin API sklepu** | dane sklepu: zamówienia, produkty, snippety… | host z `api_url` licencji, `/api/admin/v8/…` | klucz sprzedawcy z webhooka instalacji |

---

## 1. Apps API (deweloperskie)

- Wszystkie wywołania to `POST` z JSON-em zawierającym `sign` (`rules/signature-and-crypto.md`).
- Błąd biznesowy przychodzi przy HTTP 200: `{"status":"error","errors":[…]}`. Sukces to `"ok"` albo
  `"success"` (zależnie od endpointu) — sprawdzaj `status !== "error"`, nie równość z `"ok"`.

| Endpoint | Cel | Pola żądania (+ `sign`) |
|----------|-----|-------------------------|
| `/application/license` | lista licencji aplikacji | `application_id`, `developer`, opcj. `api_license`, `active` |
| `/application/license/setPrice` | opłata w modelu usage/limit | `application_id`, `developer`, `api_license`, `price` |
| `/application/installation/done` | potwierdzenie instalacji (online) | `application_id`, `developer`, `api_license` |

### Odpowiedź `application/license` (potwierdzona na panelu demo)

```jsonc
{
  "status": "success",                 // uwaga: "success", nie "ok"
  "license": [{
    "number": "…",                     // = api_license z webhooka (sekret)
    "application_id": 1234,
    "active": true, "trial": false, "test": false,
    "client": { "id": 555001, "name": "…", "lang": "pol" },   // client.id = client_id (int)
    "pricing": { … },
    "technical": {
      "installation_completed": false, // false do czasu installation/done
      "installation_completed_date": null,
      "orderd_date": "…",              // literówka jest w API
      "termination_date": null,
      "api_permissions": [ … ]
    },
    "isf": …
  }]
}
```

`installation/done` odpowiada `{"status":"ok","sign":"…"}`. Dopiero po nim startuje okres próbny,
a aplikacja pojawia się w menu panelu sprzedawcy.

### W SDK

```php
use Idosell\LaravelAppSdk\Facades\Idosell;

$apps = Idosell::apps();                         // AppsApiClient, sign dokładany automatycznie
$apps->findLicense($license->api_license);       // wpis z `license[]` o tym numerze albo null
$apps->licenses(active: true);                   // surowa odpowiedź application/license
$apps->setPrice($license->api_license, 250);     // NADPISUJE kwotę okresu (usage/limit)
```

- Nie wołaj `confirmInstallation()` sam — SDK robi to w webhooku `new-license`.
- `ApiException` przy HTTP ≠ 2xx, przekierowaniu albo `status: "error"`.
- Semantyka `setPrice`: `rules/payments-and-verification.md`.

---

## 2. Admin API sklepu (skrót)

- Host jest **inny dla każdej instalacji** — wynika z `api_url` webhooka
  (`https://clientNNNN.idosell.com/api/admin/`). Nie hardkoduj domen sprzedawców.
- Klucz przychodzi zaszyfrowany w webhooku `new-license` (`authorization_type: "key"`) — nie
  generujesz go ręcznie i nie prosisz o niego sprzedawcy. Każda bramka API ma swoje uprawnienie —
  w panelu dewelopera deklarujesz tylko te, których aplikacja potrzebuje.
  Odpowiedź 401/403 z Admin API zwykle oznacza brak zadeklarowanej bramki.
- `authorization_type: "OAuth"`: SDK nie zapisuje tokenu — pobierz go sam (`POST /authorize/accessToken`
  Admin API sklepu) i zapisz w licencji (`$license->update(['api_key' => $token])`); `adminApi()`
  wyśle wtedy `Authorization: Bearer`. Odświeżanie tokenu jest po stronie aplikacji.
- Model SaaS: wszyscy sprzedawcy na tej samej wersji systemu; poprawki niemal codziennie, z naciskiem
  na wsteczną kompatybilność w ramach wersji API. Testuj na panelu **demo**
  (https://www.idosell.com/pl/demo/zamowienie-dostepu-do-demo/).
- Nie każda operacja panelu ma odpowiednik w API: wykonaj akcję ręcznie, znajdź endpoint w spec,
  przetestuj na demo; braki zgłoś do wsparcia (https://idosell.readme.io/docs/support).

Endpointy, pola, snippety, feed i klient SDK → skill **`idosell-admin-api`**.

---

## Weryfikacja u źródła

- https://idosell.readme.io/reference, https://idosell.readme.io/llms.txt
- `https://{domena-panelu}/api/doc/admin/v8/json` (spec danego sklepu)

Nie zakładaj pól, których nie widzisz w dokumentacji ani w odpowiedzi.
