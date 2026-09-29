<?php

namespace Idosell\LaravelAppSdk\Actions;

use Idosell\LaravelAppSdk\Events\LicenseActivated;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Services\ApiKeyDecryptor;
use Idosell\LaravelAppSdk\Services\AppsApiClient;

/**
 * Aktywacja licencji po webhooku `url_webhook_new_license`.
 *
 * Kolejność ma znaczenie: najpierw zapisujemy licencję (żeby nie stracić danych
 * sprzedawcy, gdy padnie kolejny krok), potem — dla aplikacji online — potwierdzamy
 * instalację, a na końcu emitujemy zdarzenie dla logiki aplikacji.
 *
 * Idempotentne: `updateOrCreate` po (client_id, application_id) obsługuje ponowne
 * dostarczenie webhooka i ponowną instalację aplikacji.
 *
 * @see https://idosell.readme.io/docs/how-to-prepare-the-application
 */
class ActivateLicense
{
    public function __construct(
        private readonly ApiKeyDecryptor $decryptor,
        private readonly AppsApiClient $appsApi,
    ) {}

    /**
     * @param  array<string, mixed>  $data  zwalidowany payload webhooka
     */
    public function handle(array $data): IdosellLicense
    {
        // Aplikacje downloadable nie przysyłają tego pola — domyślnie klasyczny klucz API.
        $authorizationType = (string) ($data['authorization_type'] ?? '') ?: 'key';

        // Aplikacje downloadable nie dostają `api_key`; przy OAuth pole zawiera token,
        // którym zarządza aplikacja — deszyfrujemy tylko klasyczny klucz Admin API.
        $apiKey = $authorizationType === 'key' && !empty($data['api_key'])
            ? $this->decryptor->decrypt((string) $data['api_key'])
            : null;

        $license = IdosellLicense::updateOrCreate(
            [
                'client_id' => (int) $data['client_id'],
                'application_id' => (int) $data['application_id'],
            ],
            array_filter([
                'api_url' => $data['api_url'] ?? null,
                'api_license' => $data['api_license'] ?? null,
                'api_key' => $apiKey,
                'authorization_type' => $authorizationType,
                'active' => true,
                'meta' => [
                    'contact_data' => $data['contact_data'] ?? null,
                    'selected_shops' => $data['selected_shops'] ?? null,
                ],
            ], static fn ($value): bool => $value !== null),
        );

        $isNew = $license->wasRecentlyCreated;

        if ($this->shouldConfirmInstallation()) {
            $this->appsApi->confirmInstallation((string) $license->api_license);
            $license->update(['installation_confirmed' => true]);
        }

        LicenseActivated::dispatch($license, $data, $isNew);

        return $license;
    }

    /**
     * Finalizacji `installation/done` wymagają wyłącznie aplikacje typu online.
     */
    private function shouldConfirmInstallation(): bool
    {
        return config('idosell.apps.type', 'online') === 'online';
    }
}
