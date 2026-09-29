<?php

namespace Idosell\LaravelAppSdk\Events;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Licencja zaraz zostanie wyłączona — moment na posprzątanie zasobów w sklepie.
 *
 * To JEDYNY pewny moment, w którym klucz Admin API sprzedawcy jest jeszcze ważny.
 * Usuwaj tu snippety, feedy i inne rzeczy założone w panelu; nasłuchuj synchronicznie
 * (bez kolejki), bo po zakończeniu obsługi webhooka klucz może przestać działać.
 *
 * Listener nie powinien rzucać wyjątkiem — porażka sprzątania nie może zablokować
 * deaktywacji (IdoSell i tak uzna aplikację za odinstalowaną).
 */
class LicenseDeactivating
{
    use Dispatchable;

    public function __construct(public readonly IdosellLicense $license) {}
}
