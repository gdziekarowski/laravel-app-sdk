<?php

namespace Idosell\LaravelAppSdk\Console;

use Idosell\LaravelAppSdk\Services\SignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

use function Laravel\Prompts\select;

/**
 * Wysyła do własnej aplikacji webhook o kształcie takim, jaki przysyła IdoSell —
 * z prawdziwym podpisem i (dla `new-license`) z `api_key` zaszyfrowanym bieżącym IV
 * z `keyset`.
 *
 * Pozwala przejść cały cykl życia lokalnie, zanim aplikacja zostanie dodana w panelu
 * dewelopera. Świadomie NIE omijamy warstwy HTTP — chodzi o sprawdzenie realnej ścieżki
 * żądania razem z middleware.
 *
 * Wyłącznie dla środowisk nieprodukcyjnych.
 */
class SimulateWebhookCommand extends Command
{
    protected $signature = 'idosell:simulate
                            {event? : new-license|remove-license|launch}
                            {--client=990001 : client_id sprzedawcy}
                            {--application= : application_id (domyślnie z configu)}
                            {--api-url=https://demo-shop.example.com/api : adres Admin API sprzedawcy}
                            {--api-key=admin-api-key-0000000000000000 : klucz Admin API do zaszyfrowania}
                            {--api-license=LIC-SIMULATED-0000000000 : klucz licencji}
                            {--url= : Pełny adres webhooka (domyślnie z APP_URL i configu tras)}';

    protected $description = 'Symuluje webhook IdoSell na własnej aplikacji (tylko środowiska deweloperskie)';

    public function handle(SignatureService $signature): int
    {
        if (app()->isProduction()) {
            $this->components->error('Komenda jest zablokowana na produkcji.');

            return self::FAILURE;
        }

        $event = $this->argument('event') ?? select(
            label: 'Który webhook wysłać?',
            options: ['new-license', 'remove-license', 'launch'],
        );

        if (!in_array($event, ['new-license', 'remove-license', 'launch'], true)) {
            $this->components->error('Nieznane zdarzenie: '.$event);

            return self::FAILURE;
        }

        try {
            $payload = $this->payload($event, $signature);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $url = $this->url($event);

        $this->components->twoColumnDetail('POST', $url);

        $response = Http::timeout(30)->acceptJson()->post($url, $payload);

        $this->newLine();
        $this->components->twoColumnDetail('HTTP', (string) $response->status());
        $this->line(json_encode($response->json() ?? $response->body(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $ok = $response->successful() && ($response->json('status') === 'ok');

        $this->newLine();
        $ok
            ? $this->components->info('Webhook obsłużony poprawnie.')
            : $this->components->error('Aplikacja nie potwierdziła obsługi — sprawdź logi (LOG_LEVEL=debug).');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $event, SignatureService $signature): array
    {
        $base = [
            'client_id' => (int) $this->option('client'),
            'application_id' => (int) ($this->option('application') ?: config('idosell.apps.application_id')),
            'api_url' => (string) $this->option('api-url'),
            'api_license' => (string) $this->option('api-license'),
            'sign' => $signature->make(),
        ];

        if ($event !== 'new-license') {
            return $base;
        }

        return $base + [
            'api_key' => $this->encryptApiKey((string) $this->option('api-key')),
            'authorization_type' => 'key',
            'contact_data' => ['name' => 'Sklep Testowy', 'email' => 'jan.kowalski@example.com'],
            'selected_shops' => [['id' => 1, 'name' => 'Sklep 1']],
        ];
    }

    /**
     * Szyfruje klucz dokładnie tak, jak robi to platforma — AES-256-CBC z IV z `keyset`.
     * Dzięki temu deszyfracja po stronie aplikacji przechodzi tę samą ścieżkę co na żywo.
     */
    private function encryptApiKey(string $plain): string
    {
        $response = Http::timeout(5)->get((string) config('idosell.apps.keyset_url'));
        $response->throw();

        $encrypted = openssl_encrypt(
            $plain,
            'AES-256-CBC',
            (string) config('idosell.apps.application_key'),
            0,
            trim($response->body()),
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Nie udało się zaszyfrować api_key — sprawdź IDOSELL_APPLICATION_KEY.');
        }

        return base64_encode($encrypted);
    }

    private function url(string $event): string
    {
        if ($url = $this->option('url')) {
            return (string) $url;
        }

        return rtrim((string) config('app.url'), '/')
            .'/'.trim((string) config('idosell.routes.prefix'), '/')
            .'/'.$event;
    }
}
