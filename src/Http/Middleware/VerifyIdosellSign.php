<?php

namespace Idosell\LaravelAppSdk\Http\Middleware;

use Closure;
use Idosell\LaravelAppSdk\Services\SignatureService;
use Idosell\LaravelAppSdk\Support\LogChannel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Weryfikuje podpis `sign` przychodzących webhooków IdoSell Apps.
 *
 * Przy niepowodzeniu zwracamy podpisaną odpowiedź `{status:"error", sign}` i NIE
 * przepuszczamy żądania dalej — payloadowi bez ważnego podpisu nie wolno ufać.
 * Status 200 z `status: "error"` to zachowanie oczekiwane przez platformę.
 *
 * Alias: `idosell.verify-sign`.
 */
class VerifyIdosellSign
{
    public function __construct(private readonly SignatureService $signature) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sign = (string) $request->input('sign', '');

        if (!$this->signature->verify($sign)) {
            LogChannel::resolve()->warning('IdoSell webhook: nieprawidłowy podpis sign.', [
                'path' => $request->path(),
                'client_id' => $request->input('client_id'),
                'sign_present' => $sign !== '',
            ]);

            return response()->json([
                'status' => 'error',
                'sign' => $this->signature->make(),
            ]);
        }

        return $next($request);
    }
}
