# Podpis (sign) i kryptografia

Źródło: https://idosell.readme.io/docs/how-to-prepare-the-application

Każda komunikacja aplikacja ↔ IdoSell Apps jest podpisana polem `sign`. Aplikacja **online** dodatkowo
otrzymuje zaszyfrowany `api_key`, który trzeba zdeszyfrować.

## Podpis `sign`

Algorytm: **SHA-256** z trzech składników połączonych znakiem `|`:

```php
$sign = hash('sha256', $login . '|' . date('Y-m-d') . '|' . $applicationKey);
```

| Składnik | Znaczenie |
|----------|-----------|
| `$login` | login dewelopera w IdoSell Apps |
| `date('Y-m-d')` | bieżąca data (UTC/serwera platformy) w formacie `YYYY-MM-DD` |
| `$applicationKey` | unikalny klucz aplikacji (sekret z panelu dewelopera) |

### Weryfikacja przychodzącego webhooka

Odtwórz `sign` z własnych danych i porównaj **bezpiecznie** (stały czas):

```php
$expected = hash('sha256', $login . '|' . date('Y-m-d') . '|' . $applicationKey);

if (! hash_equals($expected, (string) $request->input('sign'))) {
    // odrzuć — nie ufaj payloadowi, nie loguj sekretów
    return response()->json(['status' => 'error']);
}
```

> **Nie odsyłaj `sign` przy błędnym podpisie.** Podpis nie zależy od treści żądania, więc ważny
> `sign` w odpowiedzi pozwoliłby nadawcy podpisać dowolny webhook tego dnia.

> **Uwaga na datę**: podpis zależy od `date('Y-m-d')`. Wokół północy oraz przy różnicy stref
> czasowych dopuść tolerancję (sprawdź też datę ±1 dzień), zanim uznasz podpis za niepoprawny.

### Podpisywanie odpowiedzi i żądań wychodzących

Tym samym wzorem generuj `sign` w odpowiedziach na żądania z poprawnym podpisem (`{status, sign}`)
oraz w żądaniach do Apps API
(`application/license`, `setPrice`, `installation/done`).

## Deszyfracja `api_key` (aplikacja online, `authorization_type = "key"`)

`api_key` w webhooku aktywacji jest zaszyfrowany **AES-256-CBC**. Wektor inicjujący (IV) pobiera się
z endpointu platformy, a kluczem jest `applicationKey`.

```php
$iv = trim(@file_get_contents('https://apps.idosell.com/keyset'));

$apiKey = openssl_decrypt(
    base64_decode($encryptedApiKey),  // wartość api_key z webhooka
    'AES-256-CBC',
    $applicationKey,                  // klucz aplikacji
    0,                                // domyślne opcje (dane w base64)
    $iv
);
```

Zalecenia produkcyjne (Laravel) — wszystkie zrealizowane w `Services\ApiKeyDecryptor`:
- Nie używaj `file_get_contents` bez timeoutu — pobieraj IV klientem HTTP z `timeout`/`retry`,
  np. `Http::timeout(5)->retry(3, 200)`.
- **Pobieraj IV przy każdej deszyfracji.** W trybie CBC inny IV nie powoduje błędu, zmienia tylko
  pierwszy blok (16 bajtów) wyniku, więc błąd wyszedłby dopiero przy wywołaniu Admin API.
- Zdeszyfrowany `api_key` to **sekret** — zapisz zaszyfrowany (cast `encrypted`) i nigdy nie loguj.

## Reguły bezpieczeństwa (RODO)

- `sign`, `applicationKey`, `api_key`, `api_license`, `app_secret`, tokeny OAuth → **sekrety**.
  Trzymaj w `.env` / menedżerze sekretów; nie commituj i nie umieszczaj w logach/odpowiedziach.
- W logach maskuj wartości (np. tylko długość lub hash), nigdy pełny sekret ani dane osobowe
  z `contact_data`.
- Zawsze weryfikuj `sign` **przed** przetworzeniem payloadu.
