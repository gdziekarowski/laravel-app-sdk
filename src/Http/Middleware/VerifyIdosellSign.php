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
 * Przy niepowodzeniu zwracamy HTTP 200 z `{status:"error"}` i nie przepuszczamy żądania dalej.
 *
 * Odpowiedź celowo NIE zawiera `sign`: podpis nie zależy od treści żądania, więc ważny `sign`
 * odesłany nieuwierzytelnionemu nadawcy pozwoliłby mu podpisać dowolny webhook tego dnia.
 *
 * Alias: `idosell.verify-sign`.
 */
class VerifyIdosellSign
{
    public function __construct(private readonly SignatureService $signature) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sign = $request->input('sign');
        $sign = is_string($sign) ? $sign : '';

        if (!$this->signature->verify($sign)) {
            LogChannel::resolve()->warning('IdoSell webhook: nieprawidłowy podpis sign.', [
                'path' => $request->path(),
                'client_id' => $request->input('client_id'),
                'sign_present' => $sign !== '',
            ]);

            return response()->json(['status' => 'error']);
        }

        return $next($request);
    }
}
