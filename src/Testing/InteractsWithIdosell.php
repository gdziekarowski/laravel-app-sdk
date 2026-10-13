<?php

namespace Idosell\LaravelAppSdk\Testing;

use Closure;
use Idosell\LaravelAppSdk\Services\SignatureService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Pomocniki do testowania integracji w aplikacji korzystającej z SDK.
 *
 * Dają to, czego brakuje najbardziej przy pisaniu pierwszych testów: poprawny podpis,
 * `api_key` zaszyfrowany tak jak robi to platforma i atrapę Apps API. Dzięki temu test
 * przechodzi PEŁNĄ ścieżkę (trasa → middleware → akcja), a nie tylko wywołanie akcji.
 *
 * ```php
 * uses(InteractsWithIdosell::class);
 *
 * it('instaluje aplikację', function () {
 *     $this->withIdosellConfig();
 *     $this->fakeIdosellApps();
 *
 *     $this->postIdosellNewLicense(['client_id' => 555001])
 *         ->assertOk()
 *         ->assertJson(['status' => 'ok']);
 * });
 * ```
 *
 * Wszystkie dane są syntetyczne — nigdy nie wklejaj tu payloadów z produkcji (RODO).
 */
trait InteractsWithIdosell
{
    /**
     * Testowy zestaw danych aplikacji. Klucz musi mieć 32 bajty (AES-256).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function withIdosellConfig(array $overrides = []): void
    {
        config(array_merge([
            'idosell.apps.developer' => 'dev-login',
            'idosell.apps.application_key' => str_repeat('K', 32),
            'idosell.apps.application_id' => 4242,
            'idosell.apps.type' => 'online',
            'idosell.apps.base_url' => 'https://apps.idosell.com/api',
            'idosell.apps.keyset_url' => 'https://apps.idosell.com/keyset',
            'idosell.apps.sign_date_tolerance_days' => 1,
            // Domyślne domeny paneli + syntetyczne panele z payloadów testowych (`demo-shop.example.com`).
            'idosell.admin_api.allowed_hosts' => '*.idosell.com,*.iai-shop.com,*.example.com',
        ], $overrides));
    }

    /**
     * Prawidłowy podpis dla bieżącej konfiguracji.
     */
    protected function idosellSign(?string $date = null): string
    {
        return app(SignatureService::class)->make($date);
    }

    /**
     * Testowy wektor inicjujący — 16 bajtów, jak wymaga AES-256-CBC.
     */
    protected function idosellIv(): string
    {
        return str_repeat('a', 16);
    }

    /**
     * Szyfruje klucz dokładnie tak, jak robi to platforma (AES-256-CBC + base64).
     */
    protected function idosellEncryptedApiKey(string $plain): string
    {
        return base64_encode((string) openssl_encrypt(
            $plain,
            'AES-256-CBC',
            (string) config('idosell.apps.application_key'),
            0,
            $this->idosellIv(),
        ));
    }

    /**
     * Atrapa endpointów platformy: `keyset`, `installation/done` i `application/license`.
     *
     * Dokłada `Http::preventStrayRequests()`, żeby test nie wyszedł niezauważenie
     * do prawdziwego API.
     *
     * `application/license` odpowiada licencją o numerze z zapytania, przypisaną do `$clientId` —
     * tak przechodzi weryfikacja właściciela licencji przy aktywacji.
     *
     * @param  array<string, mixed>  $extra  dodatkowe reguły Http::fake()
     */
    protected function fakeIdosellApps(array $extra = [], int $clientId = 555001): void
    {
        Http::fake(array_merge([
            '*keyset' => Http::response($this->idosellIv(), 200),
            '*installation/done' => Http::response(['status' => 'ok'], 200),
            '*application/license' => $this->idosellLicenseResponse($clientId),
        ], $extra));

        Http::preventStrayRequests();
    }

    /**
     * Atrapa `application/license` (kształt wg dokumentacji): licencja o numerze z zapytania
     * należąca do `$clientId`. Do użycia we własnym `Http::fake()`.
     */
    protected function idosellLicenseResponse(int $clientId = 555001): Closure
    {
        return static fn (Request $request) => Http::response([
            'status' => 'success',
            'license' => [[
                'number' => (string) ($request->data()['api_license'] ?? ''),
                'application_id' => (int) config('idosell.apps.application_id'),
                'active' => true,
                'client' => ['id' => $clientId, 'name' => 'Sklep Testowy', 'lang' => 'pol'],
                'technical' => ['installation_completed' => true],
            ]],
        ], 200);
    }

    /**
     * @param  array<string, mixed>  $payload  nadpisuje pola domyślnego payloadu
     */
    protected function postIdosellNewLicense(array $payload = []): TestResponse
    {
        return $this->postJson($this->idosellWebhookUrl('new-license'), array_merge([
            'client_id' => 555001,
            'application_id' => (int) config('idosell.apps.application_id'),
            'api_url' => 'https://demo-shop.example.com/api',
            'api_key' => $this->idosellEncryptedApiKey('admin-api-key-0000000000000000'),
            'api_license' => 'LIC-TEST-0000000000000000',
            'authorization_type' => 'key',
            'selected_shops' => [['id' => 1, 'name' => 'Sklep 1']],
            'sign' => $this->idosellSign(),
        ], $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postIdosellRemoveLicense(array $payload = []): TestResponse
    {
        return $this->postJson($this->idosellWebhookUrl('remove-license'), array_merge([
            'client_id' => 555001,
            'application_id' => (int) config('idosell.apps.application_id'),
            'api_url' => 'https://demo-shop.example.com/api',
            'api_license' => 'LIC-TEST-0000000000000000',
            'sign' => $this->idosellSign(),
        ], $payload));
    }

    /**
     * Webhook `launch` zwraca link do panelu tylko dla istniejącej, aktywnej licencji —
     * utwórz ją w teście wcześniej (np. przez `postIdosellNewLicense()`).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function postIdosellLaunch(array $payload = []): TestResponse
    {
        return $this->postJson($this->idosellWebhookUrl('launch'), array_merge([
            'client_id' => 555001,
            'application_id' => (int) config('idosell.apps.application_id'),
            'api_url' => 'https://demo-shop.example.com/api',
            'api_license' => 'LIC-TEST-0000000000000000',
            'sign' => $this->idosellSign(),
        ], $payload));
    }

    private function idosellWebhookUrl(string $event): string
    {
        $name = config('idosell.routes.name', 'idosell.webhooks.').$event;

        return route($name);
    }
}
