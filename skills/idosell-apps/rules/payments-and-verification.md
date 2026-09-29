# Płatności i proces weryfikacji

Źródła: https://idosell.readme.io/docs/available-payment-models,
https://idosell.readme.io/docs/what-is-the-type-of-license,
https://idosell.readme.io/docs/how-does-the-application-verification-process-work,
https://idosell.readme.io/docs/how-to-check-the-application,
https://idosell.readme.io/docs/how-can-i-test-an-unpublished-application

## Modele płatności

Platforma zajmuje się rozliczeniami (subskrypcje, faktury sprzedawcy). Dostępne dwa modele:

### 1. Fixed price (stała cena)
- Jedna, stała opłata cykliczna. Naliczana automatycznie na początku każdego okresu rozliczeniowego
  na fakturę sprzedawcy.
- Używaj, gdy aplikacja ma prostą, niezmienną cenę.

### 2. Usage / Limit (na podstawie zużycia)
- Elastyczny: progi, opłata bazowa + dodatki, w pełni własne rozliczenia.
- Kwotę zgłaszasz endpointem `POST apps.idosell.com/api/application/license/setPrice`.
- Moment zgłaszania kontroluje deweloper (początek cyklu, codziennie, koniec cyklu).

#### Semantyka `setPrice` (KRYTYCZNE)
- Każde wywołanie **nadpisuje** poprzednią wartość — **nie kumuluje**.
  - Przykład: zgłoszenie `100 PLN`, potem `250 PLN` → naliczone `250 PLN` (nie `350 PLN`).
  - Aby "dodać" opłatę, zgłaszaj **narastającą sumę** w danym cyklu.
- **Limit wydatków**: sprzedawca ustala maksymalną kwotę, na jaką się zgadza. **Nigdy jej nie przekraczaj**
  przy zgłaszaniu opłaty (bramka i tak odrzuci kwoty ponad limit).
- Sprzedawca może zmienić limit w dowolnym momencie, ale nowy limit nie może być niższy niż już naliczone
  opłaty ani niż minimalny miesięczny limit.
- **Minimalny miesięczny limit**: deweloper może ustawić dolną wartość, poniżej której sprzedawca nie
  obniży limitu (dot. nowych i istniejących subskrypcji).
- Prowadź własną historię opłat (transparentność wobec sprzedawcy).

> Typ licencji (płatna/próbna itp.) i szczegóły: https://idosell.readme.io/docs/what-is-the-type-of-license

## Proces weryfikacji

Weryfikacja startuje przy **zgłoszeniu aplikacji do publikacji** i przebiega w dwóch fazach:

**1. Kontrola formalna** — aplikacja musi mieć:
- nazwę oraz opis (krótki i rozszerzony),
- materiały w **dwóch językach: polskim i angielskim**, ikonę i zdjęcia,
- kategorię, cenę i długość bezpłatnego okresu próbnego (trial).

**2. Weryfikacja techniczna** — aplikacja musi:
- mieć linki do swojej wersji roboczej,
- poprawnie komunikować się z IdoSell Admin API,
- obsługiwać webhooki z IdoSell AppStore,
- być kompatybilna z weryfikowanymi bramkami API.

**Powiadomienia**: o postępie i uwagach dowiesz się przez panel dewelopera i e-mail. Dodatkowe
weryfikacje mogą nastąpić przed publikacją lub w trakcie eksploatacji.

## Testowanie przed publikacją

- Panel dewelopera ma **wbudowany app tester**, uruchamiany w procesie zgłoszenia do publikacji;
  sprawdza komunikację aplikacji z platformą.
- Można go uruchamiać **wielokrotnie, bez limitu**; scenariusze zależą od typu aplikacji
  (online/downloadable).
- Do testów integracji z Admin API użyj **Sandbox Demo** (patrz `rules/api-and-auth.md`).
- Testowanie webhooków: https://idosell.readme.io/docs/testing-webhooks-in-idosell-apps
- Problemy/kontakt: https://idosell.readme.io/docs/support

## Checklista przed zgłoszeniem

- [ ] Materiały PL + EN (nazwa, opisy, ikona, zdjęcia, kategoria).
- [ ] Ustawiony model płatności (fixed / usage) + cena i trial; przy usage — logika `setPrice` z respektem limitu.
- [ ] Webhooki cyklu życia (`new_license`/`remove_license`) i uruchomienia zwracają poprawny `sign`.
- [ ] (online) Finalizacja `installation/done` działa.
- [ ] Komunikacja z Admin API na właściwej wersji i z minimalnymi uprawnieniami.
- [ ] Przejście wbudowanego app testera.
- [ ] Brak sekretów/PII w logach i repo.
