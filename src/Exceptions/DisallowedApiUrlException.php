<?php

namespace Idosell\LaravelAppSdk\Exceptions;

/**
 * `api_url` nie przeszedł polityki adresów (`Support\ApiUrlPolicy`) — SDK nie wyśle pod niego
 * klucza Admin API sprzedawcy.
 *
 * `$reason` pozwala odróżnić przyczyny w logach i obsłudze błędów:
 * - `invalid`     — brak hosta albo nieparsowalny adres,
 * - `scheme`      — inny schemat niż https,
 * - `not_public`  — adres IP, `localhost` albo domena sieci wewnętrznej,
 * - `not_allowed` — host spoza listy `idosell.admin_api.allowed_hosts`.
 */
class DisallowedApiUrlException extends ConfigurationException
{
    public const INVALID = 'invalid';

    public const SCHEME = 'scheme';

    public const NOT_PUBLIC = 'not_public';

    public const NOT_ALLOWED = 'not_allowed';

    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly ?string $host = null,
    ) {
        parent::__construct($message);
    }

    public static function make(string $reason, ?string $host): self
    {
        $message = match ($reason) {
            self::SCHEME => 'Adres `api_url` musi używać https.',
            self::NOT_PUBLIC => sprintf('Adres `api_url` (%s) nie jest publiczną domeną panelu — adresy IP, `localhost` i domeny wewnętrzne są odrzucane.', $host),
            self::NOT_ALLOWED => sprintf(
                'Host `%s` z `api_url` nie jest na liście dozwolonych domen paneli. Jeśli to prawidłowy panel IdoSell, '
                .'dopisz go do IDOSELL_ADMIN_API_ALLOWED_HOSTS (wzorce po przecinku, np. `*.idosell.com,%s`) '
                .'albo ustaw pustą wartość, żeby wyłączyć sprawdzanie listy.',
                $host,
                $host,
            ),
            default => 'Adres `api_url` jest nieprawidłowy — nie można ustalić hosta panelu.',
        };

        return new self($message, $reason, $host);
    }
}
