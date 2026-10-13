# Podpis (sign) i kryptografia

Źródło: https://idosell.readme.io/docs/how-to-prepare-the-application

> **W Laravelu z `idosell/laravel-app-sdk` nie implementuj tego sam.** Webhooki weryfikuje
> middleware `idosell.verify-sign`, podpis do własnego kodu daje `Idosell::sign()` /
> `Idosell::verify($sign)`, a klucz deszyfruje `Idosell::decryptApiKey($encrypted)` (robi to już
> webhook `new-license`). Poniższe opisy to wiedza o protokole — dla innych stosów i do diagnozy.

## Podpis `sign`

```php
$sign = hash('sha256', $developer . '|' . date('Y-m-d') . '|' . $applicationKey);
```

| Składnik | Znaczenie |
|----------|-----------|
| `$developer` | login dewelopera w IdoSell Apps (`IDOSELL_DEVELOPER`) |
| `date('Y-m-d')` | bieżąca data; strefa czasowa IdoSell nie jest udokumentowana |
| `$applicationKey` | klucz aplikacji z panelu dewelopera (`IDOSELL_APPLICATION_KEY`, sekret) |

Własności, o których trzeba pamiętać:

- Podpis **nie obejmuje treści żądania ani `client_id`** i jest **taki sam dla wszystkich
  sprzedawców danego dnia**. Nie ma innego podpisu treści (HMAC). Ważny `sign` potwierdza tylko, że
  nadawca zna klucz aplikacji — tożsamość instalacji potwierdza `api_license`
  (`rules/app-lifecycle.md` → Zasady).
- Przy weryfikacji dopuść datę ±1 dzień (przełom doby, różne strefy). SDK:
  `IDOSELL_SIGN_DATE_TOLERANCE=1` — przechwycony podpis działa więc do ~72 h; nie zmieniaj bez powodu.
- Porównuj w stałym czasie (`hash_equals`).

Weryfikacja (poza SDK):

```php
$valid = false;
foreach ([-1, 0, 1] as $offset) {
    $date = now()->addDays($offset)->format('Y-m-d');
    $valid = $valid || hash_equals(hash('sha256', "$developer|$date|$applicationKey"), (string) $request->input('sign'));
}

if (! $valid) {
    return response()->json(['status' => 'error']);   // bez sign, bez logowania wartości
}
```

- **Nie odsyłaj `sign` przy błędnym podpisie** — ważny podpis w odpowiedzi pozwoliłby nadawcy
  podpisać dowolny webhook tego dnia.
- **Nie loguj `sign`** (ani przy sukcesie, ani przy błędzie) — loguj najwyżej fakt obecności pola.
- Tym samym wzorem podpisujesz odpowiedzi na poprawne webhooki (`{status, sign}`) oraz żądania do
  Apps API (`application/license`, `setPrice`, `installation/done`).

## Deszyfracja `api_key` (online, `authorization_type = "key"`)

`api_key` z webhooka instalacji jest zaszyfrowany **AES-256-CBC** kluczem aplikacji. IV pobiera się
z `https://apps.idosell.com/keyset`. Kod referencyjny (tak robi też `ApiKeyDecryptor`):

```php
$iv = trim(Http::timeout(10)->retry(3, 200)->withoutRedirecting()->get('https://apps.idosell.com/keyset')->throw()->body());

$apiKey = openssl_decrypt(
    base64_decode($encryptedApiKey, true) ?: '',   // wartość api_key z webhooka
    'AES-256-CBC',
    $applicationKey,
    0,
    $iv
);   // false → zły klucz aplikacji albo payload z innego środowiska
```

- **Pobieraj IV przy każdej deszyfracji** (może się zmieniać). W CBC zły IV nie daje błędu, psuje
  tylko pierwsze 16 bajtów wyniku — błąd wyszedłby dopiero przy wywołaniu Admin API.
- Nie używaj `file_get_contents` bez timeoutu.
- Przy `authorization_type = "OAuth"` pole nie jest kluczem do deszyfracji — SDK go nie deszyfruje.
- Odszyfrowany klucz zapisuj zaszyfrowany (cast `encrypted`), nigdy nie loguj. Zmiana `APP_KEY`
  unieważnia zapisane klucze.

## Reguły bezpieczeństwa (RODO)

- Sekrety: `applicationKey`, `sign`, `api_key`, `api_license`, tokeny OAuth. Tylko `.env` /
  menedżer sekretów; nie commituj, nie umieszczaj w logach ani odpowiedziach.
- Do logowania struktur używaj `Support\Redactor::redact($data)` (maskuje nazwy z `logging.redact`
  i fragmenty z `logging.redact_fragments`: token, secret, password, apikey, signature…), nigdy
  surowego payloadu z `contact_data`.
- Weryfikuj `sign` **przed** przetworzeniem payloadu.
