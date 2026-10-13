<?php

namespace Idosell\LaravelAppSdk\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Błąd wywołania Apps API lub Admin API sprzedawcy.
 *
 * Komunikat celowo NIE zawiera treści odpowiedzi — payloady API zawierają dane
 * sprzedawców i klientów końcowych (RODO). Wyciągamy wyłącznie kod statusu oraz
 * komunikat błędu zwrócony przez API (`errors` / `faultString`).
 */
class ApiException extends IdosellException
{
    /**
     * @param  array<int|string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    /**
     * Buduje wyjątek z odpowiedzi HTTP.
     *
     * @param  string  $context  opis wywołania, np. 'GET /admin/v8/snippets/campaign'
     */
    public static function fromResponse(Response $response, string $context): self
    {
        $errors = self::extractErrors($response);

        return new self(
            sprintf(
                'IdoSell API: %s zakończone błędem HTTP %d%s.',
                $context,
                $response->status(),
                $errors !== [] ? ' — '.self::summarize($errors) : '',
            ),
            $response->status(),
            $errors,
        );
    }

    /**
     * Błąd zgłoszony w treści odpowiedzi mimo statusu 200 (Apps API i `partners/v2`
     * potrafią zwrócić `status: "error"` / `errors.faultString` z kodem 200).
     *
     * @param  array<int|string, mixed>  $errors
     */
    public static function fromPayload(string $context, array $errors, ?int $status = null): self
    {
        return new self(
            sprintf('IdoSell API: %s zwróciło błąd%s.', $context, $errors !== [] ? ' — '.self::summarize($errors) : ''),
            $status,
            $errors,
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function extractErrors(Response $response): array
    {
        $json = $response->json();

        if (!is_array($json)) {
            return [];
        }

        $errors = $json['errors'] ?? $json['error'] ?? null;

        return is_array($errors) ? $errors : (is_string($errors) ? [$errors] : []);
    }

    /**
     * Skraca błędy do jednej linii. Bierzemy wyłącznie pola komunikatów, nie cały payload.
     *
     * @param  array<int|string, mixed>  $errors
     */
    private static function summarize(array $errors): string
    {
        $messages = [];

        array_walk_recursive($errors, static function ($value, $key) use (&$messages): void {
            if (is_scalar($value) && in_array((string) $key, ['faultString', 'faultCode', 'message', 'code', '0'], true)) {
                $messages[] = (string) $value;
            }
        });

        if ($messages === []) {
            $messages = array_filter(array_map(
                static fn ($value): string => is_scalar($value) ? (string) $value : '',
                $errors,
            ));
        }

        return implode('; ', array_slice(array_unique($messages), 0, 3));
    }
}
