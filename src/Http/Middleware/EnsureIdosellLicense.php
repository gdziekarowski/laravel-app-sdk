<?php

namespace Idosell\LaravelAppSdk\Http\Middleware;

use Closure;
use Idosell\LaravelAppSdk\IdosellManager;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Support\LaunchParameters;
use Idosell\LaravelAppSdk\Support\LogChannel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chroni panel aplikacji otwierany w iframe panelu sprzedawcy.
 *
 * Panel IdoSell osadza aplikację z innej domeny, więc cookies sesji bywają blokowane
 * (SameSite, Safari ITP). Kontekst sprzedawcy przenosi zatem PODPISANY URL czasowy —
 * ten sam, który generuje webhook `launch` i `Idosell::panelUrl()`. Middleware:
 *
 * 1. weryfikuje podpis URL (`hasValidSignature()`; podpis obejmuje tylko URL, więc
 *    działa również dla POST formularza wysłanego na podpisany adres),
 * 2. odczytuje `client` i `application` z query stringu wg `idosell.launch.parameters`
 *    (bez `application` w URL używa `idosell.apps.application_id`),
 * 3. wymaga AKTYWNEJ licencji tej instalacji,
 * 4. udostępnia licencję: `Idosell::currentLicense()`, atrybut żądania `idosell_license`
 *    i binding `IdosellLicense` w kontenerze (wstrzykiwanie w metodę kontrolera).
 *
 * Każde niepowodzenie kończy się 403 (dla `expectsJson()` — `{"message": ...}`).
 *
 * Alias: `idosell.panel`.
 */
class EnsureIdosellLicense
{
    /**
     * Nazwa atrybutu żądania z licencją bieżącej instalacji.
     */
    public const ATTRIBUTE = 'idosell_license';

    public function __construct(
        private readonly IdosellManager $idosell,
        private readonly Container $container,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->hasValidSignature()) {
            return $this->deny($request, 'Link do panelu aplikacji jest nieprawidłowy lub wygasł. Uruchom aplikację ponownie z panelu sklepu.', 'signature');
        }

        $context = LaunchParameters::fromRequest($request);
        $clientId = $this->integer($context['client_id'] ?? null);
        $applicationId = array_key_exists('application_id', $context)
            ? $this->integer($context['application_id'])
            : null;

        if ($clientId === null || (array_key_exists('application_id', $context) && $applicationId === null)) {
            return $this->deny($request, 'Brak kontekstu sklepu w adresie. Uruchom aplikację ponownie z panelu sklepu.', 'context');
        }

        $license = $this->idosell->license($clientId, $applicationId);

        if ($license === null || !$license->active) {
            return $this->deny($request, 'Aplikacja nie jest aktywna dla tego sklepu.', 'license', $clientId);
        }

        $request->attributes->set(self::ATTRIBUTE, $license);
        $this->container->instance(IdosellLicense::class, $license);

        return $next($request);
    }

    /**
     * Liczba całkowita z parametru URL albo null, gdy wartość nie jest poprawnym identyfikatorem.
     */
    private function integer(mixed $value): ?int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : $integer;
    }

    private function deny(Request $request, string $message, string $reason, ?int $clientId = null): Response
    {
        LogChannel::resolve()->debug('IdoSell panel: odmowa dostępu.', [
            'path' => $request->path(),
            'reason' => $reason,
            'client_id' => $clientId,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}
