<?php

namespace Idosell\LaravelAppSdk\Services;

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Exceptions\ConfigurationException;
use Idosell\LaravelAppSdk\Exceptions\DecryptionException;
use Illuminate\Support\Facades\Http;

/**
 * Deszyfracja `api_key` z webhooka aktywacji licencji (aplikacja online,
 * `authorization_type = "key"`).
 *
 * Algorytm: AES-256-CBC. Kluczem jest `applicationKey`, wektor inicjujący (IV)
 * pobieramy z endpointu `keyset` platformy.
 *
 * Uwaga: IV pobieramy ZAWSZE na świeżo i celowo go NIE cache'ujemy. W trybie CBC
 * nieprawidłowy IV nie powoduje błędu — psuje wyłącznie pierwszy blok tekstu jawnego,
 * a reszta odszyfrowuje się poprawnie. Nieaktualny IV z cache dałby więc „klucz API",
 * który wygląda prawidłowo, przechodzi zapis do bazy i dopiero przy pierwszym wywołaniu
 * Admin API kończy się błędem autoryzacji — bardzo trudnym do powiązania z przyczyną.
 * Jedno dodatkowe żądanie HTTP przy instalacji aplikacji to niska cena za pewność.
 *
 * @see https://idosell.readme.io/docs/how-to-prepare-the-application
 */
class ApiKeyDecryptor
{
    private const CIPHER = 'AES-256-CBC';

    /**
     * Zwraca odszyfrowany klucz Admin API sprzedawcy.
     *
     * @throws DecryptionException gdy szyfrogramu nie da się odszyfrować
     * @throws ApiException gdy nie udało się pobrać IV z `keyset`
     */
    public function decrypt(string $encryptedApiKey): string
    {
        $plain = openssl_decrypt(
            base64_decode($encryptedApiKey, true) ?: '',
            self::CIPHER,
            $this->applicationKey(),
            0,
            $this->fetchIv(),
        );

        if ($plain === false) {
            throw new DecryptionException(
                'Nie udało się zdeszyfrować api_key. Sprawdź IDOSELL_APPLICATION_KEY '
                .'oraz czy payload pochodzi ze środowiska tej aplikacji.'
            );
        }

        return $plain;
    }

    /**
     * Pobiera wektor inicjujący (IV) z endpointu keyset.
     */
    private function fetchIv(): string
    {
        $url = (string) config('idosell.apps.keyset_url');

        if ($url === '') {
            throw ConfigurationException::missing('idosell.apps.keyset_url', 'IDOSELL_APPS_KEYSET_URL');
        }

        $response = Http::timeout((int) config('idosell.apps.timeout', 10))
            ->connectTimeout((int) config('idosell.apps.connect_timeout', 5))
            ->retry((int) config('idosell.apps.retries', 3), (int) config('idosell.apps.retry_delay', 200), throw: false)
            ->get($url);

        if ($response->failed()) {
            throw ApiException::fromResponse($response, 'GET '.$url);
        }

        return trim($response->body());
    }

    private function applicationKey(): string
    {
        $key = (string) config('idosell.apps.application_key');

        if ($key === '') {
            throw ConfigurationException::missing('idosell.apps.application_key', 'IDOSELL_APPLICATION_KEY');
        }

        return $key;
    }
}
