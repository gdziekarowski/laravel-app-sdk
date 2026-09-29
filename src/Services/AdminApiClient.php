<?php

namespace Idosell\LaravelAppSdk\Services;

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Exceptions\ConfigurationException;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Klient Admin API konkretnego sprzedawcy (https://{domena-panelu}/api).
 *
 * Instancja jest parametryzowana danymi licencji (adres + klucz), dlatego tworzymy ją
 * fabryką (`forLicense`), a nie przez kontener.
 *
 * Aktualne ścieżki i pola weryfikuj w specyfikacji danego sklepu:
 *   https://{domena}/api/doc/admin/v{X}/json
 */
class AdminApiClient
{
    public const AUTH_KEY = 'key';

    public const AUTH_OAUTH = 'OAuth';

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $authorizationType = self::AUTH_KEY,
        private readonly ?int $timeout = null,
        private readonly ?int $retries = null,
    ) {}

    /**
     * Tworzy klienta na podstawie zapisanej licencji sprzedawcy.
     *
     * `$timeout`/`$retries` warto skrócić dla wywołań, które nie mogą blokować UI
     * (np. podpowiedzi w formularzu).
     */
    public static function forLicense(IdosellLicense $license, ?int $timeout = null, ?int $retries = null): self
    {
        return new self(
            (string) $license->api_url,
            (string) $license->api_key,
            (string) ($license->authorization_type ?: self::AUTH_KEY),
            $timeout,
            $retries,
        );
    }

    /**
     * Ścieżka bramki Admin API we właściwej wersji: `admin('snippets/campaign')`
     * → `/admin/v8/snippets/campaign`.
     */
    public function admin(string $path): string
    {
        return '/admin/'.config('idosell.admin_api.version', 'v8').'/'.ltrim($path, '/');
    }

    /**
     * Ścieżka API partnerskiego: `partners('offers/feed')` → `/partners/v2/offers/feed`.
     * Ten sam host i ta sama autoryzacja co Admin API, inny prefiks.
     */
    public function partners(string $path): string
    {
        return '/partners/'.config('idosell.admin_api.partners_version', 'v2').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->send('POST', $path, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->send('PUT', $path, $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function delete(string $path, array $data = []): array
    {
        return $this->send('DELETE', $path, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $data): array
    {
        $client = $this->client();

        $response = match ($method) {
            'GET' => $client->get($path, $data),
            'POST' => $client->post($path, $data),
            'PUT' => $client->put($path, $data),
            'DELETE' => $client->delete($path, $data),
        };

        if ($response->failed()) {
            throw ApiException::fromResponse($response, $method.' '.$path);
        }

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        $timeout = $this->timeout ?? (int) config('idosell.admin_api.timeout', 15);
        $retries = $this->retries ?? (int) config('idosell.admin_api.retries', 3);

        $request = Http::baseUrl($this->baseUrl())
            ->withHeaders($this->authHeaders())
            ->timeout($timeout)
            ->connectTimeout(min((int) config('idosell.admin_api.connect_timeout', 5), $timeout))
            ->acceptJson();

        if ($retries > 0) {
            $request = $request->retry($retries, (int) config('idosell.admin_api.retry_delay', 300), throw: false);
        }

        return $request;
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        // Przy `authorization_type = OAuth` w polu `api_key` trzymamy token dostępowy;
        // jego pobranie/odświeżenie (`/authorize/accessToken`) jest po stronie aplikacji.
        return $this->authorizationType === self::AUTH_OAUTH
            ? ['Authorization' => 'Bearer '.$this->apiKey]
            : ['X-API-KEY' => $this->apiKey];
    }

    /**
     * Baza Admin API: `{scheme}://{host}/api` — wyznaczana ZAWSZE z hosta `api_url` licencji,
     * nigdy z wartości na sztywno.
     *
     * Z `api_url` bierzemy wyłącznie host, bo ścieżki wywołań zawierają już `/admin/v{X}/...`
     * lub `/partners/v{X}/...`. To odporne na niespójność wartości przekazywanych przez IdoSell
     * (`.../api`, `.../api/admin`, sam host) i zapobiega sklejeniu `/api/admin/admin/...`.
     *
     * @throws ConfigurationException gdy `api_url` nie zawiera hosta
     */
    private function baseUrl(): string
    {
        $host = parse_url($this->apiUrl, PHP_URL_HOST);

        if (empty($host)) {
            throw new ConfigurationException(
                'Licencja nie ma prawidłowego `api_url` — nie można ustalić adresu Admin API sprzedawcy.'
            );
        }

        $scheme = parse_url($this->apiUrl, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($this->apiUrl, PHP_URL_PORT);

        return $scheme.'://'.$host.($port ? ':'.$port : '').'/api';
    }
}
