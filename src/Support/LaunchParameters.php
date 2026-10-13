<?php

namespace Idosell\LaravelAppSdk\Support;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Http\Request;

/**
 * Mapowanie kontekstu instalacji na parametry podpisanego URL i z powrotem.
 *
 * Źródłem mapy jest `idosell.launch.parameters` (nazwa parametru URL => klucz payloadu,
 * domyślnie `client` => `client_id`, `application` => `application_id`). Ta sama mapa
 * obsługuje launch, middleware panelu i generowanie kolejnych linków, więc zmiana nazw
 * parametrów w configu działa spójnie we wszystkich trzech miejscach.
 */
class LaunchParameters
{
    /**
     * Parametry URL z payloadu (np. webhooka uruchomienia). Pomija klucze nieobecne w payloadzie.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function fromPayload(array $payload): array
    {
        $parameters = [];

        foreach (static::map() as $parameter => $payloadKey) {
            if (array_key_exists($payloadKey, $payload)) {
                $parameters[$parameter] = $payload[$payloadKey];
            }
        }

        return $parameters;
    }

    /**
     * Parametry URL identyfikujące instalację danej licencji.
     *
     * @return array<string, mixed>
     */
    public static function fromLicense(IdosellLicense $license): array
    {
        return static::fromPayload([
            'client_id' => (int) $license->client_id,
            'application_id' => (int) $license->application_id,
        ]);
    }

    /**
     * Klucze payloadu odczytane z query stringu żądania.
     *
     * Czytamy wyłącznie query string, nigdy body: podpis obejmuje tylko URL, więc wartość
     * z body formularza mogłaby nadpisać podpisany kontekst.
     *
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request): array
    {
        $payload = [];

        foreach (static::map() as $parameter => $payloadKey) {
            $value = $request->query($parameter);

            if ($value !== null && $value !== '') {
                $payload[$payloadKey] = $value;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    public static function map(): array
    {
        return array_map('strval', (array) config('idosell.launch.parameters', []));
    }
}
