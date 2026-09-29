<?php

namespace Idosell\LaravelAppSdk\Events;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Licencja została wyłączona (`active = false`) albo usunięta — zależnie od
 * `idosell.licenses.on_deactivation`.
 *
 * Przy trybie `delete` model nie istnieje już w bazie; jego atrybuty pozostają dostępne
 * do celów powiadomień i audytu.
 */
class LicenseDeactivated
{
    use Dispatchable;

    public function __construct(
        public readonly IdosellLicense $license,
        public readonly bool $deleted = false,
    ) {}
}
