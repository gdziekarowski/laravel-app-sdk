<?php

namespace Idosell\LaravelAppSdk\Models;

use Idosell\LaravelAppSdk\Database\Factories\IdosellLicenseFactory;
use Idosell\LaravelAppSdk\Services\AdminApiClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Licencja aplikacji IdoSell Apps przypisana do konta sprzedawcy.
 *
 * Jedna instalacja = jedna para (`client_id`, `application_id`). Wartości wrażliwe
 * (`api_key`, `api_license`) są szyfrowane castem `encrypted`, więc zmiana `APP_KEY`
 * unieważnia zapisane sekrety.
 *
 * @property int $client_id
 * @property int $application_id
 * @property string $api_url
 * @property string $api_license
 * @property string|null $api_license_hash
 * @property string|null $api_key
 * @property string $authorization_type
 * @property bool $active
 * @property bool $installation_confirmed
 * @property array<string, mixed>|null $meta
 */
class IdosellLicense extends Model
{
    /** @use HasFactory<IdosellLicenseFactory> */
    use HasFactory;

    /**
     * Jawna lista zamiast `$guarded`: przy `$guarded` innym niż `['*']` Eloquent odpytuje
     * `information_schema` o kolumny, czego nie przechodzą starsze wersje MySQL spotykane
     * na produkcji.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'application_id',
        'api_url',
        'api_license',
        'api_key',
        'authorization_type',
        'active',
        'installation_confirmed',
        'meta',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'api_key',
        'api_license',
        'api_license_hash',
    ];

    protected static function booted(): void
    {
        // Hash liczymy przy każdym zapisie nowego `api_license` — unikalny indeks
        // (application_id, api_license_hash) nie pozwala przypisać licencji dwóm instalacjom.
        static::saving(static function (self $license): void {
            if ($license->isDirty('api_license')) {
                $license->api_license_hash = static::hashApiLicense((string) $license->api_license);
            }
        });
    }

    /**
     * Deterministyczny hash `api_license` do wyszukiwania i unikalności (sam `api_license`
     * jest szyfrowany niedeterministycznie). Klucz licencji ma wysoką entropię, więc
     * zwykły sha256 nie pozwala go odtworzyć.
     */
    public static function hashApiLicense(string $apiLicense): string
    {
        return hash('sha256', $apiLicense);
    }

    public function getTable(): string
    {
        return (string) config('idosell.licenses.table', 'idosell_licenses');
    }

    public function getConnectionName(): ?string
    {
        return config('idosell.licenses.connection') ?: parent::getConnectionName();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_license' => 'encrypted',
            'api_key' => 'encrypted',
            'active' => 'boolean',
            'installation_confirmed' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * Klient Admin API tego sprzedawcy.
     */
    public function adminApi(?int $timeout = null, ?int $retries = null): AdminApiClient
    {
        return AdminApiClient::forLicense($this, $timeout, $retries);
    }

    /**
     * Sklepy wybrane przez sprzedawcę przy instalacji (`selected_shops` z webhooka).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function shops(): array
    {
        $shops = $this->meta['selected_shops'] ?? [];

        if (!is_array($shops)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $shop): array => [
                'id' => (int) ($shop['id'] ?? 0),
                'name' => (string) ($shop['name'] ?? ''),
            ],
            array_filter($shops, 'is_array'),
        ));
    }

    /**
     * Czy `$apiLicense` z webhooka to klucz tej licencji (porównanie odporne na timing).
     *
     * `sign` nie obejmuje `client_id`, więc to `api_license` — znane tylko platformie
     * i tej instalacji — potwierdza, że webhook dotyczy właśnie tego sprzedawcy.
     */
    public function matchesApiLicense(?string $apiLicense): bool
    {
        return $apiLicense !== null && $apiLicense !== ''
            && hash_equals((string) $this->api_license, $apiLicense);
    }

    /**
     * Domena panelu sprzedawcy wyciągnięta z `api_url` (np. `example-shop.iai-shop.com`).
     */
    public function domain(): ?string
    {
        return parse_url((string) $this->api_url, PHP_URL_HOST) ?: null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Zawęża do konkretnej instalacji; bez `$applicationId` używa aplikacji z configu.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForApplication(Builder $query, ?int $applicationId = null): Builder
    {
        return $query->where('application_id', $applicationId ?? (int) config('idosell.apps.application_id'));
    }

    protected static function newFactory(): Factory
    {
        return IdosellLicenseFactory::new();
    }
}
