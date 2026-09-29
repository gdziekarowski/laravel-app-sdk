<?php

namespace Idosell\LaravelAppSdk\Services;

use Idosell\LaravelAppSdk\Exceptions\ConfigurationException;
use Illuminate\Support\Facades\Date;

/**
 * Generowanie i weryfikacja podpisu `sign` — jedynego uwierzytelnienia w komunikacji
 * z IdoSell Apps.
 *
 * Wzór: sha256( login_dewelopera | Y-m-d | applicationKey ).
 *
 * Podpis zależy od bieżącej daty, więc wokół północy i przy różnicy stref czasowych
 * poprawny podpis może wskazywać sąsiedni dzień — stąd tolerancja ±N dni przy weryfikacji.
 *
 * @see https://idosell.readme.io/docs/how-to-prepare-the-application
 */
class SignatureService
{
    /**
     * Wartości podane wprost mają pierwszeństwo przed configiem (przydatne przy
     * obsłudze kilku aplikacji dewelopera w jednej instancji).
     */
    public function __construct(
        private readonly ?string $developer = null,
        private readonly ?string $applicationKey = null,
        private readonly ?int $toleranceDays = null,
    ) {}

    /**
     * Buduje podpis dla wskazanej daty (domyślnie dzisiejszej, format Y-m-d).
     *
     * @throws ConfigurationException gdy brakuje loginu dewelopera lub klucza aplikacji
     */
    public function make(?string $date = null): string
    {
        $date ??= Date::now()->format('Y-m-d');

        return hash('sha256', $this->developer().'|'.$date.'|'.$this->applicationKey());
    }

    /**
     * Weryfikuje przychodzący podpis w stałym czasie, dopuszczając tolerancję daty.
     */
    public function verify(string $sign): bool
    {
        if ($sign === '') {
            return false;
        }

        $tolerance = max(0, $this->tolerance());

        for ($offset = -$tolerance; $offset <= $tolerance; $offset++) {
            $date = Date::now()->addDays($offset)->format('Y-m-d');

            if (hash_equals($this->make($date), $sign)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Czy podpis da się w ogóle wyliczyć (konfiguracja kompletna) — używane przez `idosell:doctor`.
     */
    public function isConfigured(): bool
    {
        return (string) ($this->developer ?? config('idosell.apps.developer')) !== ''
            && (string) ($this->applicationKey ?? config('idosell.apps.application_key')) !== '';
    }

    private function developer(): string
    {
        $developer = (string) ($this->developer ?? config('idosell.apps.developer'));

        if ($developer === '') {
            throw ConfigurationException::missing('idosell.apps.developer', 'IDOSELL_DEVELOPER');
        }

        return $developer;
    }

    private function applicationKey(): string
    {
        $key = (string) ($this->applicationKey ?? config('idosell.apps.application_key'));

        if ($key === '') {
            throw ConfigurationException::missing('idosell.apps.application_key', 'IDOSELL_APPLICATION_KEY');
        }

        return $key;
    }

    private function tolerance(): int
    {
        return (int) ($this->toleranceDays ?? config('idosell.apps.sign_date_tolerance_days', 1));
    }
}
