# Webhooki zdarzeń (Admin API Webhook)

Źródła: https://idosell.readme.io/docs/api-admin-webhook,
https://idosell.readme.io/docs/list-of-supported-events, https://idosell.readme.io/docs/data-format

Poza webhookami cyklu życia licencji (`rules/app-lifecycle.md`) IdoSell wysyła **webhooki zdarzeń
biznesowych** ze sklepu (nowe zamówienie, zmiana stanu magazynowego itd.). To osobny mechanizm,
konfigurowany po stronie sklepu.

## Konfiguracja

- Webhook dodaje się **w panelu sklepu** (*Adding a Webhook — store panel*):
  https://idosell.readme.io/docs/adding-a-webhook-store-panel
- Definiujesz URL odbiorczy oraz zestaw subskrybowanych eventów.
- **Wersja API** ustawiona dla webhooka decyduje o formacie danych (payload zgodny z tą wersją Admin API).
- Niedziałające webhooki są automatycznie wyłączane
  (https://idosell.readme.io/docs/disabling-webhooks-that-dont-work); wysyłkę można wznowić.
- Historia i statystyki dostępne w panelu (https://idosell.readme.io/docs/history-and-statistics).

## Lista obsługiwanych eventów

> Zweryfikuj aktualną listę u źródła — może się zmieniać.

**Client (klient)** — uprawnienie **CMS** (odczyt), dane z `GET clients/clients`:
- `clientCreated`, `clientUpdated`

**Product (produkt)** — uprawnienie **PIM** (odczyt), dane z `POST products/products/search`:
- `productCreated`, `productPriceUpdated`, `productStockUpdated`, `productDispositionUpdated`

**Order (zamówienie)** — uprawnienie **OMS** (odczyt), dane z `GET orders/orders`:
- `orderCreated`, `orderUpdated`, `orderPaid`, `orderStatusUpdated`, `orderPackageCreated`,
  `orderFilesCreated`, `orderSaleDocumentCreated`, `orderSaleDocumentUpdated`, `orderSent`,
  `orderDelivered`, `orderCanceled`

**Return (zwrot)** — uprawnienie **OMS** (odczyt), dane z `GET returns/returns`:
- `returnCreated`, `returnUpdated`, `returnFundsConfirmed`, `returnPackageCreated`,
  `returnConfirmed`, `returnCanceled`

**RMA (reklamacja)** — uprawnienie **OMS** (odczyt), dane z `GET rma/rma`:
- `rmaCreated`, `rmaUpdated`, `rmaPackageCreated`, `rmaApproved`, `rmaRejected`

## Format danych

- Payload zdarzenia pochodzi z **odpowiedniej bramki Admin API** i ma format zgodny z wersją API
  ustawioną dla webhooka. Innymi słowy: strukturę rekordu (np. zamówienia) czytaj ze spec Admin API
  danej wersji, a nie zgaduj.
- Dostęp do danych zależy od uprawnień klucza (patrz tabela wyżej: klienci→CMS, produkty→PIM,
  zamówienia/zwroty/RMA→OMS).
- Dokładna koperta (nagłówki, sposób podpisu/weryfikacji, dokładny kształt JSON) — potwierdź w
  https://idosell.readme.io/docs/data-format i https://idosell.readme.io/reference. **Nie zakładaj**
  nagłówka podpisu ani pól bez potwierdzenia w źródle.

## Testowanie

- Sekcja *Testing webhooks in IdoSell Apps*:
  https://idosell.readme.io/docs/testing-webhooks-in-idosell-apps
- Mechanizm webhooków dla aplikacji:
  https://idosell.readme.io/docs/webhooks-mechanism-for-idosell-apps

## Implementacja

> Uwaga: to **osobny mechanizm** od webhooków cyklu życia licencji, których obsługę daje
> `idosell/laravel-app-sdk`. Odbiór zdarzeń biznesowych piszesz sam — SDK wnosi tu tylko
> klienta Admin API (do dociągania danych) i `Support\Redactor` (do bezpiecznego logowania).

- Własny kontroler + trasa; przy weryfikacji autentyczności nie zakładaj nagłówka podpisu bez
  potwierdzenia w dokumentacji (patrz „Format danych" wyżej).
- Ciężką obróbkę zdarzenia wynieś do kolejki (job) i odpowiadaj szybko. Zapewnij idempotencję
  (event może przyjść ponownie).
- Instalację, z której pochodzi zdarzenie, znajdziesz przez `Idosell::license($clientId)`.
- Nie loguj danych osobowych z payloadu (RODO) — patrz `rules/signature-and-crypto.md`.
