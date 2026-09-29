<?php

namespace Idosell\LaravelAppSdk\Http\Controllers;

use Idosell\LaravelAppSdk\Actions\ActivateLicense;
use Idosell\LaravelAppSdk\Actions\DeactivateLicense;
use Idosell\LaravelAppSdk\Events\AppLaunched;
use Idosell\LaravelAppSdk\Http\Requests\LaunchRequest;
use Idosell\LaravelAppSdk\Http\Requests\NewLicenseRequest;
use Idosell\LaravelAppSdk\Http\Requests\RemoveLicenseRequest;
use Idosell\LaravelAppSdk\Services\SignatureService;
use Idosell\LaravelAppSdk\Support\LaunchUrlResolver;
use Idosell\LaravelAppSdk\Support\LogChannel;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Webhooki cyklu życia aplikacji IdoSell Apps.
 *
 * Podpis `sign` weryfikuje wcześniej middleware `idosell.verify-sign`; każda odpowiedź
 * jest podpisana. Platforma oczekuje HTTP 200 z polem `status` — także przy błędzie.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly SignatureService $signature,
        private readonly LaunchUrlResolver $launchUrl,
    ) {}

    /**
     * Aktywacja licencji (`url_webhook_new_license`).
     */
    public function newLicense(NewLicenseRequest $request, ActivateLicense $action): JsonResponse
    {
        try {
            $action->handle($request->validated());
        } catch (Throwable $e) {
            $this->logFailure('new_license: aktywacja nieudana', $e, $request->validated());

            return $this->signed('error');
        }

        return $this->signed('ok');
    }

    /**
     * Deaktywacja licencji (`url_webhook_remove_license`).
     */
    public function removeLicense(RemoveLicenseRequest $request, DeactivateLicense $action): JsonResponse
    {
        try {
            $action->handle(
                (int) $request->validated('client_id'),
                (int) $request->validated('application_id'),
            );
        } catch (Throwable $e) {
            $this->logFailure('remove_license: deaktywacja nieudana', $e, $request->validated());

            return $this->signed('error');
        }

        return $this->signed('ok');
    }

    /**
     * Uruchomienie aplikacji w panelu sprzedawcy — zwraca URL przekierowania.
     */
    public function launch(LaunchRequest $request): JsonResponse
    {
        try {
            $redirect = $this->launchUrl->resolve($request->validated());
        } catch (Throwable $e) {
            $this->logFailure('launch: nie udało się zbudować adresu przekierowania', $e, $request->validated());

            return $this->signed('error');
        }

        AppLaunched::dispatch($request->validated(), $redirect);

        return $this->signed('ok', ['redirect' => $redirect]);
    }

    /**
     * Kontekst techniczny awarii webhooka.
     *
     * Świadomie BEZ `getMessage()` i bez payloadu: komunikat `QueryException` zawiera
     * SQL z podstawionymi bindingami (czyli `api_key` i `contact_data`) oraz dane
     * połączenia — w logu nie mogą się znaleźć.
     *
     * @param  array<string, mixed>  $validated
     */
    private function logFailure(string $message, Throwable $e, array $validated): void
    {
        LogChannel::resolve()->error('IdoSell '.$message.'.', [
            'exception' => $e::class,
            'sqlstate' => $e->getCode(),
            'driver_code' => $e instanceof QueryException ? ($e->errorInfo[1] ?? null) : null,
            'client_id' => (int) ($validated['client_id'] ?? 0),
            'application_id' => (int) ($validated['application_id'] ?? 0),
        ]);
    }

    /**
     * Buduje podpisaną odpowiedź JSON.
     *
     * @param  array<string, mixed>  $extra
     */
    private function signed(string $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'status' => $status,
            ...$extra,
            'sign' => $this->signature->make(),
        ]);
    }
}
