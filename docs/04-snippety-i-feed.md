# Snippety i feed produktowy

Dwie rzeczy, które aplikacje IdoSell robią najczęściej: wstrzykują kod na strony sklepu i wystawiają
feed produktowy systemowi zewnętrznemu.

## Snippety — wstrzykiwanie kodu

Snippet to kawałek HTML/JS wstrzykiwany na stronach sklepu. Musi należeć do kampanii, więc przepływ
jest dwustopniowy.

```php
$snippets = Idosell::snippets($license);

$campaignId = $snippets->ensureCampaign('Moja aplikacja', shopId: 1);

$snippetId = $snippets->upsert(
    $campaignId,
    'Mój pixel',
    '<script src="https://example.com/pixel.js" async></script>',
);
```

### Wyszukiwanie po tożsamości, nie po id

`ensureCampaign()` i `upsert()` szukają zasobu po **nazwie** (i sklepie/kampanii), a nie po id
zapisanym u Ciebie w bazie. To kluczowa decyzja projektowa: panel sprzedawcy jest źródłem prawdy.

Konsekwencje, które działają na Twoją korzyść:

- sprzedawca skasuje snippet ręcznie → następne wywołanie go odtworzy,
- ponowna instalacja aplikacji → aktualizacja istniejącego, nie duplikat,
- różnica między Twoją bazą a panelem → wyrównuje się przy kolejnym wywołaniu.

Dlatego **nazwy kampanii i snippetu traktuj jak identyfikatory** — zmiana nazwy w nowej wersji
aplikacji spowoduje utworzenie drugiego zasobu obok starego.

### Opcje

```php
$snippets->upsert($campaignId, 'Mój skrypt', 'console.log(1)', [
    'zone' => 'bodyEnd',        // head | bodyBegin | bodyEnd
    'type' => 'javascript',     // html | javascript | cgi
    'lang' => 'pol',
    'pages' => ['all' => 'y'],
    'display' => ['clientType' => 'all', 'screen' => 'y', 'tablet' => 'y', 'phone' => 'y'],
]);
```

Widoczność na urządzeniach (`display`) SDK wysyła jawnie przy każdym `upsert()`. Przy własnych
wywołaniach `snippets/snippets` też ustawiaj ją jawnie.

### Planowane wyłączenie

```php
$snippets->setEndDate($snippetId, '2026-12-31');  // IdoSell wyłączy snippet tego dnia
$snippets->setEndDate($snippetId, null);          // anulowanie
```

Datę wystarczy ustawić raz, póki klucz API jest ważny — dalej robi to IdoSell. To najprostszy sposób
na karencję po odinstalowaniu aplikacji: nie zależy ani od ważności klucza, ani od Twojego schedulera.

## Feed produktowy

Feed zakłada się przez API partnerskie (`partners/v2`) — ten sam host i ta sama autoryzacja.
**Plik generuje, hostuje i odświeża IdoSell**; Ty dostajesz stały publiczny URL.

```php
$feeds = Idosell::offersFeed($license);

['id' => $feedId, 'url' => $url] = $feeds->ensure([
    'shop' => 1,
    'name' => 'Feed mojej aplikacji',
    'nameKey' => OffersFeed::nameKey(1),
    'format' => 'IOF30',
    'driver' => file_get_contents(resource_path('feed/driver.xsl')),
    'description' => 'own',
    'price' => 'own',
    'picture' => 'all',
    'priceDifference' => 0,
    'accessSetting' => 'unlimited',
    'language' => 'pol',
    'currency' => 'PLN',
    'availability' => 'all',
]);
```

### Nie buduj własnego generatora XML

Własny generator to utrzymanie hostingu pliku, świeżości danych, paginacji po katalogu i wydajności
przy dużych sklepach. IdoSell zapewnia to w ramach `offers/feed`: bazowy
format IOF 3.0 przepuszczasz przez sterownik **XSLT** (`driver`), który transformuje go do schematu
wymaganego przez system docelowy.

### `nameKey` musi być stały

Z `nameKey` IdoSell buduje nazwę pliku feedu (maks. 8 znaków). Jeżeli zmieni się przy aktualizacji,
zmieni się **publiczny URL**, który mógł już zostać przekazany systemowi zewnętrznemu.

`OffersFeed::nameKey($shopId)` generuje wartość deterministycznie (`APP_KEY` + zakres + id sklepu),
więc jest stała dla sklepu i nie do odgadnięcia z zewnątrz.

### URL dociąga się osobno

`POST` i `PUT` zwracają **samo `id`**, bez adresu pliku. `ensure()` robi za Ciebie dodatkowy `GET`.
Brak URL w odpowiedzi nie znaczy, że feed nie powstał — wystarczy zapytać ponownie później.

### Odłączanie

```php
$feeds->disable($feedId, $params);  // PUT active:"no" — URL zostaje, da się wrócić
$feeds->delete($feedId);            // DELETE — pełne sprzątanie
```

Wyłączenie przy ręcznej rezygnacji, usunięcie przy odinstalowaniu aplikacji.

## Wymagane uprawnienia

Snippety wymagają zwykle `CMS`, feed i produkty — `PIM`. Dokładny zakres dla `partners/v2` potwierdź
w specyfikacji sklepu (`https://{domena}/api/doc/admin/v8/json`) i u IdoSell.
