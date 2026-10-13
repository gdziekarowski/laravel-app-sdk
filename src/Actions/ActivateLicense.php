<?php

namespace Idosell\LaravelAppSdk\Actions;

use Idosell\LaravelAppSdk\Events\LicenseActivated;
use Idosell\LaravelAppSdk\Exceptions\IdosellException;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Services\ApiKeyDecryptor;
use Idosell\LaravelAppSdk\Services\AppsApiClient;
use Idosell\LaravelAppSdk\Support\ApiUrlPolicy;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Aktywacja licencji po webhooku `url_webhook_new_license`.
 *
 * Podpis `sign` nie obejmuje treści webhooka, więc aktywację traktujemy jako
 * niezaufaną, dopóki nie potwierdzi jej Apps API:
 *
 * - aplikacja online: licencja staje się aktywna DOPIERO po udanym `installation/done`.
 *   Nowa instalacja jest zapisywana wcześniej jako nieaktywna (żeby nie stracić danych
 *   sprzedawcy, gdy padnie finalizacja), a zmiany istniejącej zapisujemy dopiero po
 *   finalizacji — nieudana próba nie nadpisuje danych ani nie przywraca licencji,
 * - zmiana panelu (`api_url`) przy zapisanym kluczu wymaga przysłania nowego, INNEGO `api_key` —
 *   inaczej stary klucz poszedłby pod nowy adres (także przy odtworzeniu przechwyconego
 *   webhooka z podmienionym `api_url`).
 *
 * - `api_license` należący do innej instalacji jest odrzucany — inaczej przechwycony
 *   webhook sprzedawcy odtworzony z innym `client_id` i własnym `api_url` dałby aktywną
 *   licencję z prawdziwym kluczem sprzedawcy wskazującą na serwer atakującego. Sprawdzamy
 *   lokalnie (hash licencji) i w Apps API (`client.id` licencji) — to drugie działa też
 *   dla aplikacji downloadable i dla licencji usuniętych z bazy.
 *
 * Idempotentne: rekord po (client_id, application_id) obsługuje ponowne dostarczenie
 * webhooka i ponowną instalację aplikacji.
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
        return $this->activate($data, retryOnConflict: true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function activate(array $data, bool $retryOnConflict): IdosellLicense
    {
        // Aplikacje downloadable nie przysyłają tego pola — domyślnie klasyczny klucz API.
        $authorizationType = (string) ($data['authorization_type'] ?? '') ?: 'key';

        // Aplikacje downloadable nie dostają `api_key`; przy OAuth pole zawiera token,
        // którym zarządza aplikacja — deszyfrujemy tylko klasyczny klucz Admin API.
        $apiKey = $authorizationType === 'key' && !empty($data['api_key'])
            ? $this->decryptor->decrypt((string) $data['api_key'])
            : null;

        $license = IdosellLicense::firstOrNew([
            'client_id' => (int) $data['client_id'],
            'application_id' => (int) $data['application_id'],
        ]);

        $confirm = $this->shouldConfirmInstallation();
        $exists = $license->exists;

        // Nieaktywny rekord bez finalizacji to pozostałość nieudanej pierwszej aktywacji —
        // dla logiki aplikacji (`isNew`) to nadal pierwsza instalacja.
        $isNew = !$exists || ($confirm && !$license->active && !$license->installation_confirmed);

        $this->guardApiUrlChange($license, (string) ($data['api_url'] ?? ''), $apiKey);
        $this->guardLicenseOwnership($license, (string) ($data['api_license'] ?? ''));
        $this->verifyLicenseOwner($data);

        $license->fill(array_filter([
            'api_url' => $data['api_url'] ?? null,
            'api_license' => $data['api_license'] ?? null,
            'api_key' => $apiKey,
            'authorization_type' => $authorizationType,
            'meta' => [
                'contact_data' => $data['contact_data'] ?? null,
                'selected_shops' => $data['selected_shops'] ?? null,
            ],
        ], static fn ($value): bool => $value !== null));

        if (!$exists) {
            // Nowa instalacja online czeka na finalizację jako nieaktywna.
            $license->active = !$confirm;

            try {
                $license->save();
            } catch (UniqueConstraintViolationException $e) {
                // Równoległe dostarczenie tego samego webhooka utworzyło już rekord —
                // przechodzimy (raz) ścieżkę istniejącej licencji. Ponowny konflikt oznacza
                // licencję innej instalacji i kończy się wyjątkiem.
                if (!$retryOnConflict) {
                    throw $e;
                }

                return $this->activate($data, retryOnConflict: false);
            }
        }

        if ($confirm) {
            // Wyjątek z finalizacji przerywa aktywację: nowa licencja zostaje nieaktywna,
            // a istniejąca — bez zmian.
            $this->appsApi->confirmInstallation((string) $license->api_license);

            $license->installation_confirmed = true;
        }

        $license->active = true;
        $license->save();

        LicenseActivated::dispatch($license, $data, $isNew);

        return $license;
    }

    /**
     * Nie pozwala skierować zapisanego klucza Admin API pod inny panel.
     *
     * Ten sam klucz pod nowym adresem odrzucamy tak samo jak brak klucza: przechwycony
     * webhook sprzedawcy (z jego zaszyfrowanym `api_key`) odtworzony z podmienionym
     * `api_url` wysłałby inaczej prawdziwy klucz na serwer atakującego.
     *
     * @throws IdosellException
     */
    private function guardApiUrlChange(IdosellLicense $license, string $apiUrl, ?string $newApiKey): void
    {
        if (!$license->exists || empty($license->api_key)) {
            return;
        }

        if (ApiUrlPolicy::origin($license->api_url) === ApiUrlPolicy::origin($apiUrl)) {
            return;
        }

        if ($newApiKey === null || hash_equals((string) $license->api_key, $newApiKey)) {
            throw new IdosellException(
                'Webhook zmienia adres panelu licencji bez nowego `api_key` — odrzucono, '
                .'żeby zapisany klucz Admin API nie trafił pod inny adres.'
            );
        }
    }

    /**
     * Ten sam `api_license` nie może należeć do dwóch instalacji (różnych `client_id`).
     *
     * @throws IdosellException
     */
    private function guardLicenseOwnership(IdosellLicense $license, string $apiLicense): void
    {
        if ($apiLicense === '') {
            return;
        }

        $taken = IdosellLicense::query()
            ->forApplication((int) $license->application_id)
            ->where('api_license_hash', IdosellLicense::hashApiLicense($apiLicense))
            ->where('client_id', '!=', (int) $license->client_id)
            ->exists();

        if ($taken) {
            throw new IdosellException(
                'Webhook przypisuje `api_license` innej instalacji niż ta, do której należy — odrzucono.'
            );
        }
    }

    /**
     * Potwierdza w Apps API, że licencja należy do tej instalacji (`client.id`, `application_id`).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws IdosellException
     */
    private function verifyLicenseOwner(array $data): void
    {
        if (!config('idosell.apps.verify_license_owner', true)) {
            return;
        }

        $entry = $this->appsApi->findLicense((string) ($data['api_license'] ?? ''));
        $client = is_array($entry['client'] ?? null) ? $entry['client'] : [];

        $matches = $entry !== null
            && isset($client['id'])
            && (int) $client['id'] === (int) $data['client_id']
            && (!isset($entry['application_id']) || (int) $entry['application_id'] === (int) $data['application_id']);

        if (!$matches) {
            throw new IdosellException(
                'Apps API nie potwierdza, że `api_license` należy do tej instalacji (`client_id`) — odrzucono.'
            );
        }
    }

    /**
     * Finalizacji `installation/done` wymagają wyłącznie aplikacje typu online.
     */
    private function shouldConfirmInstallation(): bool
    {
        return config('idosell.apps.type', 'online') === 'online';
    }
}
