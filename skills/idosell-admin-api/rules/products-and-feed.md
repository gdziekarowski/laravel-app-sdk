# Produkty i feed produktowy

Źródła: https://idosell.readme.io/reference/productsproductssearchpost,
https://idosell.readme.io/reference/productsproductsget,
https://idosell.readme.io/reference/productssynchronizationfilepost

## Feed ofertowy — `partners/v2 offers/feed`

Feed produktowy zakłada się przez **API partnerskie** `partners/v2` (inny prefiks niż `admin/v8`, ale
**ten sam host i ta sama autoryzacja `X-API-KEY`**). Plik generuje, hostuje i odświeża **IdoSell**;
aplikacja dostaje **stały publiczny URL**, który przekazuje systemowi zewnętrznemu.

```
https://{domena-panelu}/api/partners/v2/offers/feed
```

> To lepsza droga niż własny generator XML czy ręczny eksport w panelu: odpada utrzymanie generatora,
> hostowanie pliku i pilnowanie świeżości danych. Bazowy format to **IOF30** + sterownik **XSLT**
> (`driver`) transformujący go do docelowego schematu — pliku nie budujesz sam i nie musisz pobierać
> produktów.

### Metody

- `POST /partners/v2/offers/feed` — utworzenie. Body `{ "params": { … } }` → zwraca `{ "id": N }`.
- `PUT /partners/v2/offers/feed` — aktualizacja / (re)aktywacja (`active:"yes"`) / wyłączenie (`active:"no"`);
  body `{ "params": { "id": N, …, "active": … } }`.
- `GET /partners/v2/offers/feed?shop={id}` lub `?id={id}` → `{ "results": [ { "id","name","shop","url" } ] }`.
- `DELETE /partners/v2/offers/feed?id={id}` — usunięcie.

`POST`/`PUT` zwracają **samo `id`** (URL dociągnij `GET …?id=` → `results[].url`). Błąd:
`{ "errors": { "faultCode", "faultString" } }`.

### Payload `params`

`shop` (per sklep), `name` (też identyfikator feedu), `nameKey` (md5 8 zn., **stały per sklep** → URL
niezmienny), `format` (`IOF30` — jedyna wartość), `driver` (XSLT inline), `description`/`price`/`picture`
(`own|all|select`), `priceDifference`, `accessSetting` (`auto|manual|unlimited`), `accessIP` (opcj.),
`language`/`currency`/`availability`, `active` (tylko `PUT`).

W SDK klucz nazwy generuje `OffersFeed::nameKey($shopId)`.

### Idempotencja / self-healing

Panel jest źródłem prawdy: szukaj feedu **po tożsamości** (`shop` + `name`) i rób `PUT` albo `POST`,
zamiast polegać na zapamiętanym id — wtedy ręczne skasowanie feedu w panelu zostanie naprawione.
W SDK robi to `OffersFeed::ensure()`. Odłączanie: `disable()` (`PUT active:"no"`, URL zostaje)
albo `delete()` (`DELETE`).

> Wymagane uprawnienia klucza dla `partners/v2` oraz dokładne pola potwierdź w spec sklepu
> (`/api/doc/admin/v8/json`) i u IdoSell — nie zakładaj.

## Odczyt produktów (nie jest wymagany do feedu)

> Feed generuje IdoSell (patrz wyżej). Poniższe endpointy zostają jako ogólna referencja.

### `POST /products/products/search`
Paginacja i filtrowanie:
- `resultsPage` (int, od 0), `resultsLimit` (int, 1–100).
- Filtry: `productParams` (id/kod/nazwa), `productIndexes` (kod IAI / systemu zewn. / producenta),
  `productIsAvailable` (`y`/`n`), `productIsVisible` (`y`/`n`), `containsText` (opis krótki/długi).

Zwracane pola (wybrane): `productId` (kod IAI), `productDisplayedCode` (kod zewn.), `categoryName`,
`producerName`, `productRetailPrice` (brutto), `productImages` (URL-e), `availableProfile`.

> **Uwaga**: ten endpoint nie zwraca wprost `EAN` ani URL produktu. Jeśli budujesz feed samodzielnie
> (a zwykle nie powinieneś — patrz wyżej), tych pól trzeba szukać gdzie indziej, np. w
> `GET products/products` z pełniejszym zestawem pól. Zweryfikuj dostępność w spec sklepu.

### `GET /products/products`
Pełniejsze wyciąganie informacji o produktach (nieusuniętych).

## Import / synchronizacja (kierunek do IdoSell)
- `POST /products/synchronization/file` — wgranie oferty w pliku **IOF 3.0** do modułu synchronizacji.
- `PUT /products/synchronization/finishUpload` — zakończenie wgrywania plików.

To mechanizm zasilania IdoSell danymi, nie eksportu feedu na zewnątrz.

## Zasady
- Paginacja: iteruj `resultsPage` do wyczerpania (limit 100/stronę); nie ładuj wszystkiego naraz.
- Pobieraj minimalny zestaw pól potrzebny do feedu; dane produktowe mogą być poufne — nie loguj nadmiarowo.
- Dokładny zestaw pól i dostępność EAN/URL potwierdź w `/api/doc/admin/v8/json` — nie zakładaj.
