<?php

namespace Idosell\LaravelAppSdk\Actions;

use Idosell\LaravelAppSdk\Events\LicenseDeactivated;
use Idosell\LaravelAppSdk\Events\LicenseDeactivating;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Support\Collection;

/**
 * Deaktywacja licencji po webhooku `url_webhook_remove_license` (odinstalowanie
 * aplikacji albo brak płatności).
 *
 * Domyślnie licencja ZOSTAJE w bazie z `active = false`. To celowe: po odinstalowaniu
 * często trzeba jeszcze posprzątać zasoby w sklepie (snippety, feedy), a do tego
 * potrzebny jest zapisany klucz Admin API. Tryb `delete` ustaw tylko wtedy, gdy nic
 * po sobie nie zostawiasz.
 */
class DeactivateLicense
{
    /**
     * `$apiLicense` (z webhooka) zawęża deaktywację do licencji z tym kluczem — `sign` nie
     * obejmuje `client_id`, więc bez tego podpis wystarczyłby do wyłączenia dowolnej instalacji.
     * `null` pomija sprawdzenie (wywołania z własnego kodu aplikacji).
     *
     * @return Collection<int, IdosellLicense> deaktywowane licencje (pusta, gdy nic nie znaleziono)
     */
    public function handle(int $clientId, int $applicationId, ?string $apiLicense = null): Collection
    {
        $licenses = IdosellLicense::query()
            ->forClient($clientId)
            ->forApplication($applicationId)
            ->get()
            ->filter(static fn (IdosellLicense $license): bool => $apiLicense === null || $license->matchesApiLicense($apiLicense))
            ->values();

        $delete = config('idosell.licenses.on_deactivation', 'mark_inactive') === 'delete';

        foreach ($licenses as $license) {
            // Sprzątanie zasobów w sklepie MUSI pójść przed unieważnieniem licencji —
            // to ostatni moment z ważnym kluczem Admin API.
            LicenseDeactivating::dispatch($license);

            $delete
                ? $license->delete()
                : $license->update(['active' => false]);

            LicenseDeactivated::dispatch($license, $delete);
        }

        return $licenses;
    }
}
