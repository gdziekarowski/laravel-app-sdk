<?php

namespace Idosell\LaravelAppSdk;

use Closure;
use Idosell\LaravelAppSdk\Exceptions\IdosellException;
use Idosell\LaravelAppSdk\Http\Middleware\EnsureIdosellLicense;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Resources\OffersFeed;
use Idosell\LaravelAppSdk\Resources\Snippets;
use Idosell\LaravelAppSdk\Services\AdminApiClient;
use Idosell\LaravelAppSdk\Services\ApiKeyDecryptor;
use Idosell\LaravelAppSdk\Services\AppsApiClient;
use Idosell\LaravelAppSdk\Services\SignatureService;
use Idosell\LaravelAppSdk\Support\LaunchParameters;
use Idosell\LaravelAppSdk\Support\LaunchUrlResolver;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\URL;

/**
 * Jeden punkt wejścia do SDK — dostępny też przez fasadę `Idosell`.
 *
 * Wszystko, co tu jest, da się wywołać bezpośrednio na klasach serwisów; manager
 * skraca typowe ścieżki i ułatwia start bez zagłębiania się w strukturę paczki.
 */
class IdosellManager
{
    public function __construct(
        private readonly SignatureService $signature,
        private readonly ApiKeyDecryptor $decryptor,
        private readonly AppsApiClient $apps,
    ) {}

    /**
     * Podpis `sign` dla wskazanej daty (domyślnie dzisiejszej).
     */
    public function sign(?string $date = null): string
    {
        return $this->signature->make($date);
    }

    /**
     * Czy przychodzący podpis jest prawidłowy (z tolerancją daty).
     */
    public function verify(string $sign): bool
    {
        return $this->signature->verify($sign);
    }

    public function signature(): SignatureService
    {
        return $this->signature;
    }

    /**
     * Deszyfruje `api_key` z webhooka aktywacji.
     */
    public function decryptApiKey(string $encrypted): string
    {
        return $this->decryptor->decrypt($encrypted);
    }

    /**
     * Klient Apps API dewelopera (licencje, rozliczenia, finalizacja instalacji).
     */
    public function apps(): AppsApiClient
    {
        return $this->apps;
    }

    /**
     * Klient Admin API sprzedawcy dla wskazanej licencji.
     */
    public function adminApi(IdosellLicense $license, ?int $timeout = null, ?int $retries = null): AdminApiClient
    {
        return AdminApiClient::forLicense($license, $timeout, $retries);
    }

    /**
     * Znajduje licencję instalacji; bez `$applicationId` bierze aplikację z configu.
     */
    public function license(int $clientId, ?int $applicationId = null): ?IdosellLicense
    {
        return IdosellLicense::query()
            ->forClient($clientId)
            ->forApplication($applicationId)
            ->first();
    }

    /**
     * Licencja instalacji bieżącego żądania, ustawiona przez middleware `idosell.panel`.
     *
     * Poza trasą chronioną tym middleware (konsola, kolejka, inne trasy) zwraca null.
     */
    public function currentLicense(): ?IdosellLicense
    {
        $container = Container::getInstance();

        if (!$container->bound('request')) {
            return null;
        }

        $license = $container->make('request')->attributes->get(EnsureIdosellLicense::ATTRIBUTE);

        return $license instanceof IdosellLicense ? $license : null;
    }

    /**
     * Podpisany URL czasowy do trasy panelu z kontekstem instalacji (`client`, `application`).
     *
     * Służy do przenoszenia kontekstu sprzedawcy do kolejnych linków i formularzy w iframe,
     * gdzie nie można polegać na cookies sesji. Parametry kontekstu nadpisują te z
     * `$parameters`. TTL z `idosell.launch.ttl`.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws IdosellException gdy nie podano licencji i żądanie nie ma kontekstu instalacji
     */
    public function panelUrl(string $routeName, array $parameters = [], ?IdosellLicense $license = null): string
    {
        $license ??= $this->currentLicense();

        if ($license === null) {
            throw new IdosellException(
                'Brak licencji do zbudowania linku panelu. Wywołuj panelUrl() na trasie z middleware '
                .'`idosell.panel` albo przekaż licencję jawnie.'
            );
        }

        return URL::temporarySignedRoute(
            $routeName,
            now()->addMinutes((int) config('idosell.launch.ttl', 30)),
            array_merge($parameters, LaunchParameters::fromLicense($license)),
        );
    }

    /**
     * Snippety sklepu (wstrzykiwanie HTML/JS na stronach sprzedawcy).
     */
    public function snippets(IdosellLicense $license): Snippets
    {
        return new Snippets($this->adminApi($license));
    }

    /**
     * Feedy ofertowe sklepu (partners/v2 offers/feed).
     */
    public function offersFeed(IdosellLicense $license): OffersFeed
    {
        return new OffersFeed($this->adminApi($license));
    }

    /**
     * Podmienia sposób budowania adresu przekierowania po uruchomieniu aplikacji.
     *
     * @param  (Closure(array<string, mixed>): string)|null  $callback
     */
    public function resolveLaunchUrlUsing(?Closure $callback): void
    {
        LaunchUrlResolver::resolveUsing($callback);
    }
}
