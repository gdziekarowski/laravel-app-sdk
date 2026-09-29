<?php

namespace Idosell\LaravelAppSdk\Events;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Licencja została zapisana, a dla aplikacji online — instalacja potwierdzona.
 *
 * Tu wpinaj logikę uruchamianą po instalacji aplikacji u sprzedawcy (np. wstrzyknięcie
 * snippetu, założenie feedu, e-mail powitalny). Pamiętaj o idempotencji — webhook
 * aktywacji potrafi przyjść ponownie, wtedy `$isNew` jest false.
 */
class LicenseActivated
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  zwalidowany payload webhooka (api_key wciąż zaszyfrowany)
     */
    public function __construct(
        public readonly IdosellLicense $license,
        public readonly array $payload = [],
        public readonly bool $isNew = true,
    ) {}
}
