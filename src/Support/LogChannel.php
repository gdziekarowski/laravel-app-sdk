<?php

namespace Idosell\LaravelAppSdk\Support;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Kanał logowania paczki.
 *
 * Gdy `idosell.logging.channel` nie jest ustawiony, świadomie NIE wołamy
 * `Log::channel(null)`, tylko sięgamy po korzeń fasady. Dzięki temu logowanie działa
 * także wtedy, gdy aplikacja podmieni fasadę w testach (`Log::spy()`, `Log::fake()`).
 */
class LogChannel
{
    public static function resolve(): LoggerInterface
    {
        $channel = config('idosell.logging.channel');

        return $channel
            ? Log::channel($channel)
            : Log::getFacadeRoot();
    }
}
