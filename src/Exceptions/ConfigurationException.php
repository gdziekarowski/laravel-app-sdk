<?php

namespace Idosell\LaravelAppSdk\Exceptions;

/**
 * Brakująca lub niepoprawna konfiguracja paczki.
 *
 * Świadomie rzucamy zamiast działać na pustych wartościach: podpis wyliczony z pustego
 * `application_key` wygląda poprawnie, a jest bezużyteczny — taki błąd trudno zdiagnozować
 * po fakcie.
 */
class ConfigurationException extends IdosellException
{
    /**
     * @param  string  $key  klucz configu, np. `idosell.apps.developer`
     * @param  string|null  $env  odpowiadająca zmienna środowiskowa
     */
    public static function missing(string $key, ?string $env = null): self
    {
        return new self(sprintf(
            'Brak konfiguracji `%s`%s. Uzupełnij ją przed wywołaniem IdoSell Apps.',
            $key,
            $env !== null ? ' (ustaw '.$env.' w .env)' : '',
        ));
    }
}
