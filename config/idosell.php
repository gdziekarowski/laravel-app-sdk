<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dane aplikacji z panelu dewelopera (apps.idosell.com)
    |--------------------------------------------------------------------------
    |
    | `application_key` to SEKRET — trzymaj wyłącznie w .env / menedżerze sekretów,
    | nigdy w repozytorium. Z niego wyliczany jest podpis `sign` oraz deszyfrowany
    | `api_key` przychodzący w webhooku aktywacji licencji.
    |
    */

    'apps' => [
        // Identyfikator aplikacji z panelu dewelopera.
        'application_id' => env('IDOSELL_APPLICATION_ID'),

        // Login dewelopera — pierwszy składnik podpisu `sign`.
        'developer' => env('IDOSELL_DEVELOPER'),

        // Unikalny klucz aplikacji (SEKRET).
        'application_key' => env('IDOSELL_APPLICATION_KEY'),

        // 'online' (hostowana u dewelopera) albo 'downloadable'.
        // Tylko 'online' finalizuje instalację przez `application/installation/done`.
        'type' => env('IDOSELL_APP_TYPE', 'online'),

        'base_url' => env('IDOSELL_APPS_BASE_URL', 'https://apps.idosell.com/api'),

        // Endpoint zwracający wektor inicjujący (IV) do deszyfracji `api_key`.
        'keyset_url' => env('IDOSELL_APPS_KEYSET_URL', 'https://apps.idosell.com/keyset'),

        // Przy aktywacji pytamy Apps API (`application/license`), czy `api_license` z webhooka
        // należy do tego `client_id`. `sign` nie obejmuje treści webhooka, więc bez tego
        // przechwycony webhook dałoby się przypisać innej instalacji. Wyłączaj tylko awaryjnie.
        'verify_license_owner' => (bool) env('IDOSELL_VERIFY_LICENSE_OWNER', true),

        // Tolerancja daty (w dniach) przy weryfikacji przychodzącego `sign`.
        // Datę w podpisie liczymy w strefie aplikacji (`app.timezone`, domyślnie UTC), IdoSell —
        // we własnej (nieudokumentowanej). 1 = akceptujemy wczoraj/dziś/jutro: bezpieczne przy
        // różnicy stref i webhookach dostarczonych tuż po północy, ale przechwycony podpis działa
        // do ~72 h. 0 skraca to do jednego dnia kalendarzowego, lecz wymaga tej samej strefy co
        // IdoSell i odrzuci webhook podpisany przed północą, a dostarczony po niej.
        'sign_date_tolerance_days' => (int) env('IDOSELL_SIGN_DATE_TOLERANCE', 1),

        'timeout' => (int) env('IDOSELL_APPS_TIMEOUT', 10),
        'connect_timeout' => (int) env('IDOSELL_APPS_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('IDOSELL_APPS_RETRIES', 3),
        'retry_delay' => (int) env('IDOSELL_APPS_RETRY_DELAY', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin API sprzedawcy (https://{domena-panelu}/api)
    |--------------------------------------------------------------------------
    |
    | Host bierzemy zawsze z `api_url` licencji (per panel) — tu ustawiasz jedynie
    | wersje bramek i parametry transportu. Aktualne ścieżki i pola weryfikuj w
    | specyfikacji konkretnego sklepu: https://{domena}/api/doc/admin/v{X}/json
    |
    */

    'admin_api' => [
        'version' => env('IDOSELL_ADMIN_API_VERSION', 'v8'),
        'partners_version' => env('IDOSELL_PARTNERS_API_VERSION', 'v2'),

        // Domeny paneli, pod które SDK wyśle klucz Admin API sprzedawcy (`api_url` z webhooka).
        // Wzorce `*.domena` rozdzielone przecinkami; dopisz kolejne, jeśli panel ma inną domenę
        // (odrzucony `api_url` trafia do logu z hostem i podpowiedzią). Pusta wartość
        // (`IDOSELL_ADMIN_API_ALLOWED_HOSTS=`) wyłącza sprawdzanie listy. Niezależnie od listy
        // `api_url` musi mieć https i publiczną domenę (bez IP, localhost, domen wewnętrznych).
        'allowed_hosts' => env('IDOSELL_ADMIN_API_ALLOWED_HOSTS', '*.idosell.com,*.iai-shop.com'),

        'timeout' => (int) env('IDOSELL_ADMIN_API_TIMEOUT', 15),
        'connect_timeout' => (int) env('IDOSELL_ADMIN_API_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('IDOSELL_ADMIN_API_RETRIES', 3),
        'retry_delay' => (int) env('IDOSELL_ADMIN_API_RETRY_DELAY', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Przechowywanie licencji
    |--------------------------------------------------------------------------
    |
    | `api_key` i `api_license` zapisujemy zaszyfrowane (cast `encrypted`), więc
    | zmiana APP_KEY unieważnia zapisane sekrety.
    |
    | on_deactivation:
    |   'mark_inactive' — (zalecane) licencja zostaje z `active = false`. Klucz Admin
    |                     API bywa potrzebny do posprzątania zasobów po karencji.
    |   'delete'        — rekord jest usuwany razem z sekretami.
    |
    */

    'licenses' => [
        'connection' => env('IDOSELL_DB_CONNECTION'),
        'table' => env('IDOSELL_LICENSES_TABLE', 'idosell_licenses'),

        // Ładowanie migracji z paczki. Ustaw false, jeśli publikujesz je do aplikacji.
        'run_migrations' => (bool) env('IDOSELL_RUN_MIGRATIONS', true),

        'on_deactivation' => env('IDOSELL_ON_DEACTIVATION', 'mark_inactive'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trasy webhooków
    |--------------------------------------------------------------------------
    |
    | Pełne adresy do wpisania w panelu dewelopera (przy domyślnym prefiksie):
    |   Link to working application                   → {APP_URL}/api/idosell/webhooks/launch
    |   Webhook address to enable the application     → {APP_URL}/api/idosell/webhooks/new-license
    |   Webhook address to disable the application    → {APP_URL}/api/idosell/webhooks/remove-license
    |   Webhook address to modify application licence → {APP_URL}/api/idosell/webhooks/new-license
    |
    | Wyłącz (`enabled = false`), jeśli chcesz zarejestrować własne trasy.
    |
    */

    'routes' => [
        'enabled' => (bool) env('IDOSELL_ROUTES_ENABLED', true),
        'prefix' => env('IDOSELL_ROUTES_PREFIX', 'api/idosell/webhooks'),
        'middleware' => ['api'],
        'name' => 'idosell.webhooks.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Uruchomienie aplikacji w panelu sprzedawcy
    |--------------------------------------------------------------------------
    |
    | Webhook `launch` musi odpowiedzieć adresem, pod który panel przekieruje
    | sprzedawcę. Podaj nazwę własnej trasy (`route`) albo pełny URL (`url`).
    |
    | Przy `signed = true` i podanej trasie generujemy PODPISANY URL czasowy —
    | to jedyna identyfikacja sprzedawcy po stronie panelu aplikacji, więc trasy
    | panelu muszą mieć middleware `idosell.panel` (sprawdza podpis i licencję).
    |
    | `parameters` mapuje: nazwa parametru trasy => klucz z payloadu webhooka.
    |
    */

    'launch' => [
        'route' => env('IDOSELL_LAUNCH_ROUTE'),
        'url' => env('IDOSELL_LAUNCH_URL'),
        'signed' => (bool) env('IDOSELL_LAUNCH_SIGNED', true),
        'ttl' => (int) env('IDOSELL_LAUNCH_TTL', 30),
        'parameters' => [
            'client' => 'client_id',
            'application' => 'application_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logowanie
    |--------------------------------------------------------------------------
    |
    | Webhooki logujemy na poziomie `debug` PRZED weryfikacją podpisu (widać też
    | żądania odrzucone). Wartości kluczy z `redact` są maskowane — w logu nie
    | mogą wylądować sekrety ani dane osobowe (RODO).
    |
    */

    'logging' => [
        'channel' => env('IDOSELL_LOG_CHANNEL'),
        'webhooks' => (bool) env('IDOSELL_LOG_WEBHOOKS', true),
        'redact' => [
            'api_key', 'apikey', 'api_license', 'sign', 'token', 'secret', 'password',
            'contact_data', 'email', 'phone', 'phonenumber', 'nip',
        ],

        // Klucze ZAWIERAJĄCE te fragmenty też są maskowane (bez względu na wielkość liter,
        // `_` i `-`): `token` łapie `access_token`/`refreshToken`, `secret` — `client_secret` itd.
        'redact_fragments' => [
            'token', 'secret', 'password', 'passwd', 'apikey', 'privatekey', 'credential',
            'signature',
        ],
    ],

];
