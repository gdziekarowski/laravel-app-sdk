# Cykl życia aplikacji i licencji

## Instalacja (aplikacja online)

```
Sprzedawca włącza aplikację w panelu
        │
        │  POST {APP_URL}/api/idosell/webhooks/new-license
        ▼
  idosell.log-webhook      → zapis żądania do logu (sekrety zamaskowane)
  idosell.verify-sign      → weryfikacja sign; brak/zły → {status:"error"} i koniec
  NewLicenseRequest        → walidacja payloadu
        ▼
  ActivateLicense
    1. deszyfracja api_key (AES-256-CBC, IV z keyset)
    2. updateOrCreate licencji po (client_id, application_id)
    3. POST apps.idosell.com/api/application/installation/done
    4. zdarzenie LicenseActivated
        ▼
  odpowiedź { status: "ok", sign }
```

Kolejność kroków 2 i 3 jest celowa: licencję zapisujemy **zanim** odezwiemy się do platformy.
Gdy wywołanie `installation/done` się nie powiedzie, dane sprzedawcy są już u nas, webhook zwróci `error`, a ponowne
dostarczenie dokończy instalację. Odwrotna kolejność oznaczałaby utratę danych przy każdym
chwilowym problemie sieciowym.

**Twoja logika** wpina się w `LicenseActivated`:

```php
public function handle(LicenseActivated $event): void
{
    // Webhook przychodzi ponownie przy reinstalacji — $event->isNew bywa false.
    // Wszystko, co tu robisz, musi znieść powtórzenie.
    foreach ($event->license->shops() as $shop) {
        ConfigureShop::dispatch($event->license, $shop['id']);
    }
}
```

## Uruchomienie w panelu sprzedawcy

```
Sprzedawca klika „Uruchom" w panelu sklepu
        │  POST {APP_URL}/api/idosell/webhooks/launch
        ▼
  LaunchUrlResolver → podpisany URL czasowy do IDOSELL_LAUNCH_ROUTE
        ▼
  { status: "ok", redirect: "...?client=1&application=2&signature=...", sign }
        ▼
  panel przekierowuje sprzedawcę pod `redirect`
```

Panel nie przekazuje żadnego tokenu sesji — **podpis URL to całe uwierzytelnienie**. Trasa docelowa
musi weryfikować podpis — najlepiej middleware `idosell.panel` (podpis + aktywna licencja), inaczej
dowolna osoba weszłaby z dowolnym `client_id`. Szczegóły: [07-panel.md](07-panel.md).

## Odinstalowanie

```
Sprzedawca wyłącza aplikację (albo kończy się płatność)
        │  POST {APP_URL}/api/idosell/webhooks/remove-license
        ▼
  DeactivateLicense
    1. zdarzenie LicenseDeactivating   ← sprzątanie zasobów w sklepie
    2. active = false   (albo delete — zależnie od configu)
    3. zdarzenie LicenseDeactivated
        ▼
  { status: "ok", sign }
```

### Dlaczego sprzątanie idzie w `LicenseDeactivating`

Po odinstalowaniu aplikacji klucz Admin API sprzedawcy przestaje działać. To **ostatni moment**,
w którym możesz usunąć to, co aplikacja założyła w panelu.

Z tego samego powodu listener tego zdarzenia:

- **nie może być kolejkowany** — job wykonałby się po unieważnieniu klucza,
- **nie powinien rzucać wyjątkiem** — nieudane sprzątanie nie może zablokować deaktywacji; IdoSell
  i tak uzna aplikację za odinstalowaną.

```php
public function handle(LicenseDeactivating $event): void
{
    try {
        Idosell::snippets($event->license)->delete($this->snippetId($event->license));
    } catch (Throwable) {
        // best-effort — logujemy i idziemy dalej
    }
}
```

### Karencja zamiast natychmiastowego usunięcia

Jeśli chcesz zostawić sprzedawcy czas na rozmyślenie się, zamiast kasować zasoby od razu:

```php
// Zleć IdoSellowi wyłączenie snippetu za 90 dni — zrobi to sam,
// niezależnie od tego, czy klucz API będzie wtedy jeszcze ważny.
Idosell::snippets($event->license)->setEndDate($snippetId, now()->addDays(90)->format('Y-m-d'));
```

Przy takim podejściu **zostaw licencję w bazie** (`licenses.on_deactivation = mark_inactive`) —
zapisany klucz bywa potrzebny do dokończenia sprzątania. Ponowna instalacja powinna wtedy wyczyścić
zaplanowane wyłączenie (`setEndDate($id, null)`).

## Aplikacje typu downloadable

Ustaw `IDOSELL_APP_TYPE=downloadable`. Różnice:

- payload aktywacji nie zawiera `api_key` ani `authorization_type`,
- krok `installation/done` jest pomijany.

Reszta przepływu jest identyczna.

## Idempotencja — lista kontrolna

Każdy webhook może przyjść więcej niż raz.

- [ ] Instalacja zasobów w sklepie szuka ich po nazwie, nie po zapamiętanym id.
- [ ] Ponowna aktywacja nie tworzy drugiego kompletu danych.
- [ ] Deaktywacja nieznanej instalacji zwraca `ok`, a nie błąd.
- [ ] Joby wywoływane ze zdarzeń są odporne na powtórzenie.
