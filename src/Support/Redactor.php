<?php

namespace Idosell\LaravelAppSdk\Support;

/**
 * Maskowanie sekretów i danych osobowych przed zapisem do logu.
 *
 * Zachowujemy strukturę payloadu i pola techniczne (client_id, application_id, api_url,
 * authorization_type, selected_shops) — po nich diagnozuje się instalację. Wartości kluczy
 * z `idosell.logging.redact` zastępujemy informacją o długości, żeby dało się odróżnić
 * „pole puste" od „pole obecne", bez ujawniania treści.
 *
 * Oprócz dokładnych nazw (`redact`) maskujemy klucze ZAWIERAJĄCE fragment z
 * `idosell.logging.redact_fragments` (np. `token` łapie `access_token`, `refreshToken`).
 * Porównanie ignoruje wielkość liter, `_` i `-`.
 */
class Redactor
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>|null  $keys  nadpisuje listę z configu
     * @param  array<int, string>|null  $fragments  nadpisuje fragmenty z configu; przy jawnym
     *                                              `$keys` domyślnie brak fragmentów (pełna kontrola)
     * @return array<string, mixed>
     */
    public static function redact(array $data, ?array $keys = null, ?array $fragments = null): array
    {
        $fragments ??= $keys === null ? (array) config('idosell.logging.redact_fragments', []) : [];
        $keys ??= (array) config('idosell.logging.redact', []);

        $keys = array_map(static fn ($key): string => strtolower((string) $key), $keys);
        $fragments = array_values(array_filter(array_map(self::normalize(...), $fragments)));

        return self::redactWith($data, $keys, $fragments);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $fragments
     * @return array<string, mixed>
     */
    private static function redactWith(array $data, array $keys, array $fragments): array
    {
        foreach ($data as $key => $value) {
            if (self::isSensitive((string) $key, $keys, $fragments)) {
                $data[$key] = is_string($value)
                    ? '[redacted len='.strlen($value).']'
                    : '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redactWith($value, $keys, $fragments);
            }
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $fragments
     */
    private static function isSensitive(string $key, array $keys, array $fragments): bool
    {
        if (in_array(strtolower($key), $keys, true)) {
            return true;
        }

        $normalized = self::normalize($key);

        foreach ($fragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(mixed $key): string
    {
        return str_replace(['_', '-'], '', strtolower((string) $key));
    }
}
