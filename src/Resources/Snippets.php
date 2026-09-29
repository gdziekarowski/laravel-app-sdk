<?php

namespace Idosell\LaravelAppSdk\Resources;

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Services\AdminApiClient;

/**
 * Snippety — mechanizm IdoSell do wstrzykiwania własnego HTML/JS na stronach sklepu
 * (w panelu: „dodatki/wstawki"). Tędy osadza się pixele i skrypty zewnętrznych systemów.
 *
 * Snippet MUSI należeć do kampanii, więc typowy przepływ to: kampania → snippet.
 *
 * Wszystkie metody wyszukujące działają „po tożsamości" (nazwa + sklep/kampania), a nie
 * po zapamiętanym id. To świadomy wybór: panel sprzedawcy jest źródłem prawdy, a zasoby
 * bywają usuwane ręcznie. Dzięki temu `ensureCampaign()` + `upsert()` są idempotentne
 * i same odtwarzają skasowany zasób, zamiast tworzyć duplikaty.
 *
 * @see https://idosell.readme.io/reference/snippetssnippetspost-1
 */
class Snippets
{
    public function __construct(private readonly AdminApiClient $client) {}

    /**
     * Lista kampanii snippetów.
     *
     * @return array<int, array<string, mixed>>
     */
    public function campaigns(): array
    {
        return (array) ($this->client->get($this->client->admin('snippets/campaign'))['results'] ?? []);
    }

    /**
     * Szuka kampanii po nazwie; z `$shopId` wymaga dodatkowo przypisania do tego sklepu.
     */
    public function findCampaign(string $name, ?int $shopId = null): ?int
    {
        foreach ($this->campaigns() as $campaign) {
            if (($campaign['name'] ?? null) !== $name || empty($campaign['id'])) {
                continue;
            }

            if ($shopId !== null
                && !in_array($shopId, array_map('intval', (array) ($campaign['shop'] ?? [])), true)) {
                continue;
            }

            return (int) $campaign['id'];
        }

        return null;
    }

    /**
     * Tworzy kampanię; z `$shopId` ogranicza ją do jednego sklepu.
     *
     * @param  array<string, mixed>  $overrides  dodatkowe pola kampanii
     */
    public function createCampaign(string $name, ?int $shopId = null, array $overrides = []): int
    {
        $campaign = ['name' => $name, 'active' => 'y'] + $overrides;

        if ($shopId !== null) {
            $campaign['shop'] = [$shopId];
        }

        return $this->resultId(
            $this->client->post($this->client->admin('snippets/campaign'), [
                'params' => ['campaigns' => [$campaign]],
            ]),
            'kampanii snippetów',
        );
    }

    /**
     * Zwraca id kampanii — istniejącej albo nowo utworzonej.
     */
    public function ensureCampaign(string $name, ?int $shopId = null): int
    {
        return $this->findCampaign($name, $shopId) ?? $this->createCampaign($name, $shopId);
    }

    public function deleteCampaign(int $campaignId): void
    {
        $this->client->delete($this->client->admin('snippets/campaign'), [
            'params' => ['campaigns' => [['id' => $campaignId]]],
        ]);
    }

    /**
     * Lista snippetów.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return (array) ($this->client->get($this->client->admin('snippets/snippets'))['results'] ?? []);
    }

    /**
     * Szuka snippetu po nazwie w obrębie kampanii.
     */
    public function find(string $name, int $campaignId): ?int
    {
        foreach ($this->all() as $snippet) {
            if (($snippet['name'] ?? null) === $name
                && (int) ($snippet['campaign'] ?? 0) === $campaignId
                && !empty($snippet['id'])) {
                return (int) $snippet['id'];
            }
        }

        return null;
    }

    /**
     * Tworzy snippet albo aktualizuje istniejący o tej samej nazwie w kampanii.
     *
     * Wielokrotne wywołanie nie mnoży snippetów i za każdym razem wymusza `active`
     * oraz widoczność na urządzeniach.
     *
     * Opcje (wszystkie mają sensowne domyślne): `zone` (head|bodyBegin|bodyEnd),
     * `type` (html|javascript|cgi), `lang`, `active`, `pages`, `display`, `extra`.
     *
     * @param  array<string, mixed>  $options
     * @return int id snippetu
     */
    public function upsert(int $campaignId, string $name, string $code, array $options = []): int
    {
        $snippetId = $this->find($name, $campaignId);

        $snippet = [
            'campaign' => $campaignId,
            'name' => $name,
            'active' => $options['active'] ?? 'y',
            'type' => $options['type'] ?? 'html',
            'body' => [
                ['lang' => $options['lang'] ?? 'pol', 'body' => $code],
            ],
            'zone' => $options['zone'] ?? 'head',
            'pages' => $options['pages'] ?? ['all' => 'y'],

            // Bez jawnego `display` API domyślnie ustawia 'n' i snippet po prostu się nie ładuje.
            // (`screen/tablet/phone` nie ma w schemacie OpenAPI, ale API je przyjmuje i zwraca.)
            'display' => $options['display'] ?? [
                'clientType' => 'all',
                'screen' => 'y',
                'tablet' => 'y',
                'phone' => 'y',
            ],
        ] + (array) ($options['extra'] ?? []);

        if ($snippetId !== null) {
            $snippet['id'] = $snippetId;
        }

        $payload = ['params' => ['snippets' => [$snippet]]];

        $response = $snippetId !== null
            ? $this->client->put($this->client->admin('snippets/snippets'), $payload)
            : $this->client->post($this->client->admin('snippets/snippets'), $payload);

        return $this->resultId($response, 'snippetu');
    }

    /**
     * Ustawia (lub czyści) natywną datę wyłączenia snippetu.
     *
     * Nieoczywista, ale bardzo użyteczna własność: IdoSell wyłączy snippet w tym dniu
     * SAM. Datę wystarczy ustawić raz, póki klucz Admin API jest ważny — np. w webhooku
     * deaktywacji, żeby dać sprzedawcy karencję. Dalej nie zależy to już ani od ważności
     * klucza, ani od naszego schedulera.
     *
     * @param  string|null  $date  data 'Y-m-d' albo null (czyści datę — snippet zostaje)
     */
    public function setEndDate(int $snippetId, ?string $date): void
    {
        $this->client->put($this->client->admin('snippets/snippets'), [
            'params' => [
                'snippets' => [[
                    'id' => $snippetId,
                    'dateEnd' => $date !== null
                        ? ['defined' => 'y', 'date' => $date]
                        : ['defined' => 'n', 'date' => null],
                ]],
            ],
        ]);
    }

    public function delete(int $snippetId): void
    {
        $this->client->delete($this->client->admin('snippets/snippets'), [
            'params' => ['snippets' => [['id' => $snippetId]]],
        ]);
    }

    /**
     * Wyciąga id z odpowiedzi (`results[0].id`) albo rzuca, gdy API zgłosiło błąd walidacji.
     *
     * Endpointy snippetów potrafią zwrócić HTTP 200/207 z błędem w `results[].errors` —
     * bez tej kontroli zapisalibyśmy „sukces" bez utworzonego zasobu.
     *
     * @param  array<string, mixed>  $response
     */
    private function resultId(array $response, string $what): int
    {
        $result = $response['results'][0] ?? null;
        $id = is_array($result) ? ($result['id'] ?? null) : null;

        if (empty($id) || !empty($result['errors'])) {
            throw ApiException::fromPayload(
                'utworzenie/aktualizacja '.$what,
                is_array($result['errors'] ?? null) ? $result['errors'] : [],
            );
        }

        return (int) $id;
    }
}
