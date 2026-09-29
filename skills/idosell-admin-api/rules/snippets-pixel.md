# Snippety — wstrzykiwanie kodu / pixel

Źródła: https://idosell.readme.io/reference/snippetssnippetspost-1,
https://idosell.readme.io/reference/snippetscampaignpost-1

Snippety to mechanizm IdoSell do wstrzykiwania własnego HTML/JS/CGI na stronach sklepu (w panelu:
„dodatki/wstawki") — to tędy osadza się **pixele i skrypty systemów zewnętrznych**.

## Endpointy

| Metoda | Ścieżka | Cel |
|--------|---------|-----|
| `GET` | `/snippets/campaign` | Lista kampanii snippetów (także usunięte, read-only) |
| `POST` | `/snippets/campaign` | Utwórz kampanię (grupa snippetów) |
| `PUT` | `/snippets/campaign` | Aktualizuj kampanię |
| `DELETE` | `/snippets/campaign` | Usuń kampanię |
| `GET` | `/snippets/snippets` | Lista snippetów |
| `POST` | `/snippets/snippets` | Utwórz snippet(y) |
| `PUT` | `/snippets/snippets` | Aktualizuj snippet(y) |
| `DELETE` | `/snippets/snippets` | Usuń snippet(y) |
| `GET` | `/snippets/cookies` | Definicje cookies powiązane ze snippetami |

Snippet **musi należeć do kampanii** (`campaign` jest wymagane) — najpierw utwórz kampanię, potem snippet.

## `POST /snippets/snippets` — struktura żądania

Tablica snippetów w `params.snippets` (1–100 pozycji):

```jsonc
{
  "params": {
    "snippets": [
      {
        "campaign": 123,                 // WYMAGANE — id kampanii
        "name": "Mój pixel",
        "active": "y",                   // "y" | "n"
        "type": "javascript",            // "html" | "javascript" | "cgi"
        "body": [
          { "lang": "pol", "body": "<KOD>" }   // właściwy kod (per język)
        ],
        "zone": "head",                  // "head" | "bodyBegin" | "bodyEnd"
        "pages": {
          "all": "y",                    // "y" = wszystkie strony
          "pages": [],
          "url": []
        },
        "display": {
          "clientType": "all",           // all|unregistered|registered|retailer|wholesaler
          "screen": "y", "tablet": "y", "phone": "y"
        },
        "sources": {
          "direct": { "active": "y", "id": 0 }
          // search, advert — analogicznie
        }
      }
    ]
  }
}
```

**Kluczowe pola:**
- `zone` — miejsce wstrzyknięcia: `head`, `bodyBegin`, `bodyEnd`.
- `type` — rodzaj kodu; dla skryptów użyj `javascript` (lub `html`, jeśli wstawiasz pełne tagi `<script>`).
- `body[].body` — treść kodu (per `lang`).
- `pages.all` — `"y"` dla całego sklepu.
- `campaign` — wymagane id kampanii.

Odpowiedź: `200`/`207` z tablicą `results` (id snippetów + ewentualne błędy walidacji).

## Przepis: osadzenie pixela

1. `POST /snippets/campaign` → utwórz kampanię (grupę snippetów), zapamiętaj jej `id`.
   Ogranicz ją do konkretnego sklepu polem `shop`, jeśli sprzedawca ma ich kilka.
2. `POST /snippets/snippets` z:
   - `campaign` = id z kroku 1,
   - `type` = `html` (pełne tagi `<script>`) lub `javascript` (sam kod),
   - `zone` = `head`,
   - `pages.all` = `"y"`,
   - `display` — **ustaw jawnie** widoczność na urządzeniach (`screen`, `tablet`, `phone`: `y`),
   - `body[].body` = treść skryptu.
3. Zweryfikuj na froncie sklepu, że skrypt faktycznie się ładuje.

W SDK całość (wraz z wyszukaniem istniejących zasobów) robi:

```php
$snippets = Idosell::snippets($license);
$snippetId = $snippets->upsert($snippets->ensureCampaign('Moja kampania', 1), 'Mój pixel', $kod);
```

Dezaktywacja: `PUT` (`active:"n"`) lub `DELETE`. Zanim utworzysz snippet, sprawdź, czy już nie
istnieje — inaczej przy ponownej instalacji aplikacji zrobisz duplikat.

> Dokładny komplet pól (`sources`, `pages.pages`, warianty `lang`) potwierdź w spec sklepu
> (`/api/doc/admin/v8/json`) i reference — nie zakładaj pól spoza dokumentacji.
