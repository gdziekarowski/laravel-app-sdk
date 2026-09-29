<?php

namespace Idosell\LaravelAppSdk\Support;

/**
 * Maskowanie sekretów i danych osobowych przed zapisem do logu.
 *
 * Zachowujemy strukturę payloadu i pola techniczne (client_id, application_id, api_url,
 * authorization_type, selected_shops) — po nich diagnozuje się instalację. Wartości kluczy
 * z `idosell.logging.redact` zastępujemy informacją o długości, żeby dało się odróżnić
 * „pole puste" od „pole obecne", bez ujawniania treści.
 */
class Redactor
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>|null  $keys  nadpisuje listę z configu
     * @return array<string, mixed>
     */
    public static function redact(array $data, ?array $keys = null): array
    {
        $keys ??= (array) config('idosell.logging.redact', []);
        $keys = array_map(static fn ($key): string => strtolower((string) $key), $keys);

        foreach ($data as $key => $value) {
            if (is_array($value) && !in_array(strtolower((string) $key), $keys, true)) {
                $data[$key] = self::redact($value, $keys);

                continue;
            }

            if (in_array(strtolower((string) $key), $keys, true)) {
                $data[$key] = is_string($value)
                    ? '[redacted len='.strlen($value).']'
                    : '[redacted]';
            }
        }

        return $data;
    }
}
