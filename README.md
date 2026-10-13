# IdoSell App SDK dla Laravela

Pakiet `idosell/laravel-app-sdk` do budowy aplikacji [IdoSell Apps](https://apps.idosell.com) —
aplikacji, które sprzedawcy instalują w panelu sklepu IdoSell. SDK robi za Ciebie:

- obsługę webhooków instalacji, uruchomienia i odinstalowania (z weryfikacją podpisu),
- bezpieczne przechowywanie kluczy sprzedawców,
- dostęp do panelu aplikacji otwieranego w iframe panelu sklepu,
- klienta Admin API sklepu i Apps API IdoSell,
- narzędzia do testów i diagnostyki.

Ty piszesz tylko panel aplikacji i to, co ma się dziać przy instalacji u sprzedawcy.

Wymagania: PHP 8.2+ (Laravel 13: PHP 8.3+), Laravel 11, 12 lub 13, `ext-json`, `ext-openssl`.

## Instalacja

```bash
composer require idosell/laravel-app-sdk
php artisan idosell:install
php artisan migrate
```

**Zacznij od [Pierwsza aplikacja krok po kroku](docs/01-pierwsza-aplikacja.md).**

## Dokumentacja

1. [Pierwsza aplikacja krok po kroku](docs/01-pierwsza-aplikacja.md) — od zera do aplikacji w sklepie testowym
2. [Jak to działa](docs/02-jak-to-dziala.md) — słowniczek i diagramy
3. [Panel aplikacji](docs/03-panel.md) — trasy, linki, formularze, CSRF
4. [Zdarzenia](docs/04-zdarzenia.md) — Twój kod przy instalacji i odinstalowaniu
5. [Admin API i Apps API](docs/05-admin-api.md) — wywołania API sklepu, snippety, feed, opłaty
6. [Testowanie](docs/06-testowanie.md) — testy automatyczne i test lokalny
7. [Produkcja i typowe błędy](docs/07-produkcja-i-bledy.md) — checklista i rozwiązywanie problemów
8. [Referencja](docs/08-referencja.md) — wszystkie zmienne `.env`, komendy, opcje zaawansowane

Zmiany: [CHANGELOG.md](CHANGELOG.md). Licencja MIT.
