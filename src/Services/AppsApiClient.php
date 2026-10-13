<?php

namespace Idosell\LaravelAppSdk\Services;

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Exceptions\ConfigurationException;
use Idosell\LaravelAppSdk\Support\ApiUrlPolicy;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Klient deweloperskiego Apps API (https://apps.idosell.com/api).
 *
 * Każde żądanie jest podpisywane polem `sign`. API zgłasza błędy biznesowe w treści odpowiedzi
 * (`status: "error"`), także przy HTTP 200 — sprawdzamy oba.
 *
 * @see https://idosell.readme.io/docs/access-to-the-api
 */
class AppsApiClient
{
    public function __construct(private readonly SignatureService $signature) {}

    /**
     * Potwierdza zakończenie instalacji aplikacji online.
     *
     * @return array<string, mixed>
     */
    public function confirmInstallation(string $apiLicense): array
    {
        return $this->post('/application/installation/done', [
            'api_license' => $apiLicense,
            'application_id' => $this->applicationId(),
            'developer' => $this->developer(),
        ]);
    }

    /**
     * Lista licencji aplikacji — opcjonalnie zawężona do jednej licencji lub stanu aktywności.
     *
     * @return array<string, mixed>
     */
    public function licenses(?string $apiLicense = null, ?bool $active = null): array
    {
        return $this->post('/application/license', array_filter([
            'application_id' => $this->applicationId(),
            'developer' => $this->developer(),
            'api_license' => $apiLicense,
            'active' => $active,
        ], static fn ($value): bool => $value !== null));
    }

    /**
     * Licencja o numerze `$apiLicense` z `application/license` albo null, gdy API jej nie zwróciło.
     *
     * Odpowiedź wg dokumentacji: `{status, license: [{number, application_id, active,
     * client: {id, name, lang}, technical: {installation_completed, ...}, ...}]}`.
     *
     * @return array<string, mixed>|null
     *
     * @see https://idosell.readme.io/docs/api-endpoints
     */
    public function findLicense(string $apiLicense): ?array
    {
        $response = $this->licenses($apiLicense);
        $entries = $response['license'] ?? $response['licenses'] ?? [];

        if (!is_array($entries)) {
            return null;
        }

        // Pojedynczy obiekt zamiast listy.
        if (array_key_exists('number', $entries) || array_key_exists('client', $entries)) {
            $entries = [$entries];
        }

        $entries = array_values(array_filter($entries, 'is_array'));

        foreach ($entries as $entry) {
            if (isset($entry['number']) && hash_equals((string) $entry['number'], $apiLicense)) {
                return $entry;
            }
        }

        // Zapytanie jest zawężone do `api_license` — jedyny wpis bez numeru to ta licencja.
        return count($entries) === 1 && !isset($entries[0]['number']) ? $entries[0] : null;
    }

    /**
     * Zgłasza opłatę w modelu usage/limit.
     *
     * UWAGA: `setPrice` NADPISUJE poprzednią wartość w bieżącym okresie rozliczeniowym —
     * nie kumuluje. Żeby „doliczyć" opłatę, wyślij narastającą sumę. Nigdy nie przekraczaj
     * limitu wydatków ustawionego przez sprzedawcę.
     *
     * @param  float|int|string  $price
     * @return array<string, mixed>
     *
     * @see https://idosell.readme.io/docs/available-payment-models
     */
    public function setPrice(string $apiLicense, float|int|string $price): array
    {
        return $this->post('/application/license/setPrice', [
            'application_id' => $this->applicationId(),
            'developer' => $this->developer(),
            'api_license' => $apiLicense,
            'price' => $price,
        ]);
    }

    /**
     * Wywołanie dowolnego endpointu Apps API (pole `sign` dokładane automatycznie).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload): array
    {
        ApiUrlPolicy::assertRelativePath($path);

        $payload['sign'] = $this->signature->make();

        $response = $this->client()->post($path, $payload);

        if ($response->failed() || $response->redirect()) {
            throw ApiException::fromResponse($response, 'POST '.$path);
        }

        $json = $response->json() ?? [];

        // Apps API sygnalizuje błędy biznesowe w treści, przy statusie 200.
        if (($json['status'] ?? null) === 'error') {
            throw ApiException::fromPayload('POST '.$path, (array) ($json['errors'] ?? []), $response->status());
        }

        return $json;
    }

    private function client(): PendingRequest
    {
        $baseUrl = (string) config('idosell.apps.base_url');

        if ($baseUrl === '') {
            throw ConfigurationException::missing('idosell.apps.base_url', 'IDOSELL_APPS_BASE_URL');
        }

        return Http::baseUrl($baseUrl)
            ->timeout((int) config('idosell.apps.timeout', 10))
            ->connectTimeout((int) config('idosell.apps.connect_timeout', 5))
            ->retry(
                (int) config('idosell.apps.retries', 3),
                (int) config('idosell.apps.retry_delay', 200),
                throw: false,
            )
            ->acceptJson()
            // Przekierowanie zabrałoby `sign` na inny host.
            ->withoutRedirecting();
    }

    private function applicationId(): int
    {
        $id = (int) config('idosell.apps.application_id');

        if ($id === 0) {
            throw ConfigurationException::missing('idosell.apps.application_id', 'IDOSELL_APPLICATION_ID');
        }

        return $id;
    }

    private function developer(): string
    {
        $developer = (string) config('idosell.apps.developer');

        if ($developer === '') {
            throw ConfigurationException::missing('idosell.apps.developer', 'IDOSELL_DEVELOPER');
        }

        return $developer;
    }
}
