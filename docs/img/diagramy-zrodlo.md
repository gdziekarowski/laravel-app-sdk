# Źródła diagramów (dla opiekunów SDK)

Diagramy w dokumentacji to pliki `docs/img/diagram-1…6.svg` generowane z tego pliku. Po zmianie
diagramu wygeneruj je ponownie (z katalogu głównego repo):

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/data minlag/mermaid-cli \
  -i docs/img/diagramy-zrodlo.md -o docs/img/diagram.md \
  -c docs/img/mermaid-config.json -C docs/img/mermaid.css -b white -s 2 \
  && rm docs/img/diagram.md
```

Kolejność diagramów = numer pliku SVG. Diagramy muszą odpowiadać kodowi (`ActivateLicense`,
`WebhookController`, `EnsureIdosellLicense`, `AdminApiClient`, `DeactivateLicense`).

## 1. Przegląd (docs/02)

```mermaid
sequenceDiagram
    participant B as Przeglądarka sprzedawcy
    participant I as IdoSell
    participant A as Twoja aplikacja (SDK)
    participant AP as Apps API
    participant ADM as Admin API sklepu

    rect rgba(99,102,241,0.07)
        Note over I,AP: Instalacja
        I->>A: Sprzedawca instaluje aplikację
        A->>AP: Potwierdź licencję
        A-->>I: OK
    end

    rect rgba(16,185,129,0.07)
        Note over B,A: Uruchomienie
        B->>I: Otwiera aplikację w panelu sklepu
        I->>A: Poproś o adres panelu
        A-->>I: Podpisany link
        I-->>B: Otwórz link w iframe
        B->>A: Panel aplikacji
    end

    rect rgba(245,158,11,0.08)
        Note over A,ADM: Praca
        A->>ADM: Twoje wywołania Admin API
        A-->>B: Widok panelu
    end

    rect rgba(239,68,68,0.06)
        Note over I,A: Odinstalowanie
        I->>A: Sprzedawca odinstalowuje aplikację
        A-->>I: OK
    end
```

## 2. Instalacja (docs/02)

```mermaid
sequenceDiagram
    autonumber
    participant I as IdoSell
    participant S as SDK
    participant AP as Apps API
    box rgba(16,185,129,0.12) Twój kod
        participant L as Listener LicenseActivated
    end

    I->>S: Webhook new-license
    S->>S: Sprawdza podpis i adres panelu
    S->>AP: Czy licencja należy do tego sklepu?
    S->>AP: installation/done (aplikacje online)
    S->>S: Zapisuje licencję jako aktywną
    S->>L: Zdarzenie LicenseActivated
    L->>L: Konfiguracja u sprzedawcy
    S-->>I: status ok
    Note over I,S: Każda nieudana kontrola kończy się odpowiedzią error, przyczyna jest w logu
```

## 3. Uruchomienie (docs/02)

```mermaid
sequenceDiagram
    autonumber
    participant B as Przeglądarka sprzedawcy
    participant I as IdoSell
    participant S as SDK
    box rgba(16,185,129,0.12) Twój kod
        participant C as Kontroler panelu
    end

    B->>I: Otwiera aplikację w panelu sklepu
    I->>S: Webhook launch
    S-->>I: Podpisany link do panelu (30 min)
    I-->>B: Otwiera link w iframe
    B->>S: GET /panel?…&signature=…
    S->>S: Sprawdza podpis i licencję
    S->>C: Licencja sprzedawcy
    C-->>B: Widok panelu
    Note over B,C: Linki i formularze w panelu twórz przez idosell_route()
```

## 4. Odinstalowanie (docs/02)

```mermaid
sequenceDiagram
    autonumber
    participant I as IdoSell
    participant S as SDK
    box rgba(16,185,129,0.12) Twój kod
        participant L as Listener LicenseDeactivating
    end
    participant ADM as Admin API sklepu

    I->>S: Webhook remove-license
    S->>S: Sprawdza podpis i licencję
    S->>L: Zdarzenie LicenseDeactivating
    L->>ADM: Usuń swoje zasoby (klucz jeszcze działa)
    S->>S: Licencja nieaktywna
    S-->>I: status ok
```

## 5. Wywołanie Admin API (docs/05)

```mermaid
flowchart LR
    A(["<b>Wywołanie<br/>Admin API</b>"]) --> C1["<b>Instalacja potwierdzona</b><br/><small>aplikacje online</small>"]
    C1 --> C2["<b>https</b>"]
    C2 --> C3["<b>Publiczna domena</b><br/><small>bez IP i domen wewnętrznych</small>"]
    C3 --> C4["<b>Domena na liście paneli</b><br/><small>IDOSELL_ADMIN_API_ALLOWED_HOSTS</small>"]
    C4 --> C5["<b>Ścieżka względna</b>"]
    C5 --> G["<b>Żądanie do panelu</b><br/><small>X-API-KEY, bez przekierowań</small>"]
    G -- 2xx --> OK(["<b>JSON</b>"])
    G -. "błąd" .-> X6["<b>ApiException</b>"]

    classDef check fill:#f8fafc,stroke:#94a3b8,stroke-width:1.5px,color:#0f172a
    classDef bad fill:#fef2f2,stroke:#f87171,stroke-width:1.5px,color:#7f1d1d
    classDef good fill:#ecfdf5,stroke:#34d399,stroke-width:1.5px,color:#064e3b
    classDef key fill:#eef2ff,stroke:#818cf8,stroke-width:1.5px,color:#1e1b4b
    class C1,C2,C3,C4,C5 check
    class X6 bad
    class OK good
    class A,G key
```

## 6. Sekrety (docs/07)

```mermaid
flowchart LR
    subgraph SERVER["Serwer aplikacji"]
        direction TB
        K["<b>IDOSELL_APPLICATION_KEY</b><br/><small>.env / menedżer sekretów</small>"]
        AK["<b>APP_KEY</b><br/><small>Laravel</small>"]
        SIGN["<b>sign</b><br/><small>podpis webhooków</small>"]
        subgraph DBX["Baza: idosell_licenses"]
            direction TB
            APIKEY["<b>api_key</b><br/><small>zaszyfrowany</small>"]
            LIC["<b>api_license</b><br/><small>zaszyfrowany</small>"]
        end
        K -- wylicza --> SIGN
        K -- "odszyfrowuje z webhooka" --> APIKEY
        AK -- "szyfruje w bazie" --> DBX
    end

    URL["<b>Podpisany link panelu</b><br/><small>ważny 30 min</small>"]
    BR2["<b>Iframe panelu</b><br/><small>przeglądarka sprzedawcy</small>"]

    subgraph NEVER["Nigdy tutaj"]
        direction TB
        LOGS["Logi aplikacji, serwera WWW,<br/>narzędzia do błędów"]
        BR["sign, api_license, api_key<br/>w przeglądarce"]
        REF["Nagłówek Referer"]
        FOREIGN["Hosty spoza listy paneli"]
    end

    SERVER -. "maskowanie w logach" .-x LOGS
    SERVER -. "webhooki tylko serwer–serwer" .-x BR
    URL -. "Referrer-Policy: no-referrer" .-x REF
    APIKEY -. "lista paneli, bez przekierowań" .-x FOREIGN
    URL -- "tylko do sprzedawcy" --> BR2

    classDef secret fill:#eef2ff,stroke:#818cf8,stroke-width:1.5px,color:#1e1b4b
    classDef never fill:#fef2f2,stroke:#f87171,stroke-width:1.5px,color:#7f1d1d
    classDef ok fill:#ecfdf5,stroke:#34d399,stroke-width:1.5px,color:#064e3b
    class K,AK,SIGN,APIKEY,LIC secret
    class LOGS,BR,REF,FOREIGN never
    class URL,BR2 ok
    style SERVER fill:#f5f7ff,stroke:#c7d2fe,stroke-width:1.5px,color:#3730a3
    style DBX fill:#ffffff,stroke:#a5b4fc,stroke-width:1.5px,color:#3730a3
    style NEVER fill:#fff5f5,stroke:#fecaca,stroke-width:1.5px,color:#991b1b
```
