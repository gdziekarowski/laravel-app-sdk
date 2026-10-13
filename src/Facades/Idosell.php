<?php

namespace Idosell\LaravelAppSdk\Facades;

use Idosell\LaravelAppSdk\IdosellManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string sign(?string $date = null)
 * @method static bool verify(string $sign)
 * @method static \Idosell\LaravelAppSdk\Services\SignatureService signature()
 * @method static string decryptApiKey(string $encrypted)
 * @method static \Idosell\LaravelAppSdk\Services\AppsApiClient apps()
 * @method static \Idosell\LaravelAppSdk\Services\AdminApiClient adminApi(\Idosell\LaravelAppSdk\Models\IdosellLicense $license, ?int $timeout = null, ?int $retries = null)
 * @method static \Idosell\LaravelAppSdk\Models\IdosellLicense|null license(int $clientId, ?int $applicationId = null)
 * @method static \Idosell\LaravelAppSdk\Models\IdosellLicense|null currentLicense()
 * @method static string panelUrl(string $routeName, array $parameters = [], ?\Idosell\LaravelAppSdk\Models\IdosellLicense $license = null)
 * @method static \Idosell\LaravelAppSdk\Resources\Snippets snippets(\Idosell\LaravelAppSdk\Models\IdosellLicense $license)
 * @method static \Idosell\LaravelAppSdk\Resources\OffersFeed offersFeed(\Idosell\LaravelAppSdk\Models\IdosellLicense $license)
 * @method static void resolveLaunchUrlUsing(?\Closure $callback)
 *
 * @see IdosellManager
 */
class Idosell extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IdosellManager::class;
    }
}
