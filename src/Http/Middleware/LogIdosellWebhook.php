<?php

namespace Idosell\LaravelAppSdk\Http\Middleware;

use Closure;
use Idosell\LaravelAppSdk\Support\LogChannel;
use Idosell\LaravelAppSdk\Support\Redactor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loguje KAŻDE żądanie na webhooki IdoSell — PRZED weryfikacją podpisu, więc widać
 * też żądania odrzucone (zły lub brakujący `sign`). To zwykle jedyny sposób, żeby
 * zobaczyć, co naprawdę przysłała platforma podczas pierwszej instalacji.
 *
 * Poziom `debug`: to ślad przebiegu, nie problem do rozwiązania. Żeby go zobaczyć,
 * ustaw na czas diagnozy `LOG_LEVEL=debug`.
 *
 * Sekrety i dane osobowe są maskowane (zob. `idosell.logging.redact`).
 *
 * Alias: `idosell.log-webhook`.
 */
class LogIdosellWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('idosell.logging.webhooks', true)) {
            return $next($request);
        }

        LogChannel::resolve()->debug('IdoSell webhook ▶ '.$request->method().' /'.ltrim($request->path(), '/'), [
            'route' => $request->route()?->getName(),
            'content_type' => $request->header('Content-Type'),
            'payload' => Redactor::redact($request->all()),
        ]);

        $response = $next($request);

        LogChannel::resolve()->debug(
            'IdoSell webhook ◀ /'.ltrim($request->path(), '/').' → HTTP '.$response->getStatusCode(),
            ['route' => $request->route()?->getName()],
        );

        return $response;
    }
}
