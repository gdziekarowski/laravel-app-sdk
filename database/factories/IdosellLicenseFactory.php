<?php

namespace Idosell\LaravelAppSdk\Database\Factories;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabryka licencji do testów aplikacji integrującej.
 *
 * Dane są WYŁĄCZNIE syntetyczne — nigdy nie przenoś tu wartości z produkcji (RODO).
 *
 * @extends Factory<IdosellLicense>
 */
class IdosellLicenseFactory extends Factory
{
    protected $model = IdosellLicense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => $this->faker->unique()->numberBetween(100000, 999999),
            'application_id' => (int) (config('idosell.apps.application_id') ?: 4242),
            'api_url' => 'https://demo-shop.example.com/api',
            'api_license' => 'LIC-'.$this->faker->regexify('[A-Z0-9]{24}'),
            'api_key' => 'admin-api-key-'.$this->faker->regexify('[a-z0-9]{16}'),
            'authorization_type' => 'key',
            'active' => true,
            'installation_confirmed' => true,
            'meta' => [
                'selected_shops' => [
                    ['id' => 1, 'name' => 'Sklep 1'],
                ],
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    public function oauth(): static
    {
        return $this->state(fn (): array => ['authorization_type' => 'OAuth']);
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $shops
     */
    public function withShops(array $shops): static
    {
        return $this->state(fn (array $attributes): array => [
            'meta' => array_merge((array) ($attributes['meta'] ?? []), ['selected_shops' => $shops]),
        ]);
    }
}
