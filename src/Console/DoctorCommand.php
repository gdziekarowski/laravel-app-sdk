<?php

namespace Idosell\LaravelAppSdk\Console;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Services\AppsApiClient;
use Idosell\LaravelAppSdk\Services\SignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Diagnostyka integracji — sprawdza to, o co najczęściej rozbija się pierwsze wdrożenie:
 * kompletność konfiguracji, dostępność `keyset`, poprawność podpisu wobec Apps API,
 * zarejestrowane trasy i istnienie tabeli licencji.
 *
 * `--live` wykonuje realne wywołania do apps.idosell.com.
 */
class DoctorCommand extends Command
{
    protected $signature = 'idosell:doctor {--live : Wykonaj realne wywołania do apps.idosell.com}';

    protected $description = 'Sprawdza konfigurację i gotowość integracji z IdoSell Apps';

    /** @var array<int, array{label: string, ok: bool, detail: string}> */
    private array $results = [];

    public function handle(SignatureService $signature, AppsApiClient $apps): int
    {
        $this->components->info('IdoSell App SDK — diagnostyka');

        $this->checkConfig();
        $this->checkSignature($signature);
        $this->checkRoutes();
        $this->checkDatabase();

        if ($this->option('live')) {
            $this->checkKeyset();
            $this->checkAppsApi($apps);
        } else {
            $this->components->twoColumnDetail(
                'Wywołania na żywo',
                '<fg=gray>pominięte (dodaj --live)</>',
            );
        }

        $failed = array_filter($this->results, static fn (array $result): bool => !$result['ok']);

        $this->newLine();

        if ($failed === []) {
            $this->components->info('Konfiguracja wygląda poprawnie.');

            return self::SUCCESS;
        }

        $this->components->error(sprintf('Problemów do naprawienia: %d.', count($failed)));

        return self::FAILURE;
    }

    private function checkConfig(): void
    {
        $this->result(
            'application_id',
            (int) config('idosell.apps.application_id') > 0,
            (int) config('idosell.apps.application_id') > 0 ? 'ok' : 'ustaw IDOSELL_APPLICATION_ID',
        );

        $this->result(
            'developer',
            (string) config('idosell.apps.developer') !== '',
            (string) config('idosell.apps.developer') !== '' ? 'ok' : 'ustaw IDOSELL_DEVELOPER',
        );

        $this->result(
            'application_key',
            (string) config('idosell.apps.application_key') !== '',
            (string) config('idosell.apps.application_key') !== '' ? 'ustawiony (nie pokazujemy)' : 'ustaw IDOSELL_APPLICATION_KEY',
        );

        $launchConfigured = config('idosell.launch.route') || config('idosell.launch.url');
        $this->result(
            'launch.route / launch.url',
            (bool) $launchConfigured,
            $launchConfigured ? 'ok' : 'brak adresu przekierowania po uruchomieniu aplikacji',
        );
    }

    private function checkSignature(SignatureService $signature): void
    {
        try {
            $sign = $signature->make();

            $this->result('podpis sign', $signature->verify($sign), 'sha256, '.strlen($sign).' znaków');
        } catch (Throwable $e) {
            $this->result('podpis sign', false, $e->getMessage());
        }
    }

    private function checkRoutes(): void
    {
        if (!config('idosell.routes.enabled', true)) {
            $this->result('trasy webhooków', true, 'wyłączone w configu — zakładam własne');

            return;
        }

        $prefix = (string) config('idosell.routes.name', 'idosell.webhooks.');

        foreach (['new-license', 'remove-license', 'launch'] as $name) {
            $exists = Route::has($prefix.$name);

            $this->result(
                'trasa '.$prefix.$name,
                $exists,
                $exists ? '/'.trim((string) config('idosell.routes.prefix'), '/').'/'.$name : 'brak',
            );
        }
    }

    private function checkDatabase(): void
    {
        $table = (string) config('idosell.licenses.table', 'idosell_licenses');

        try {
            $schema = Schema::connection(config('idosell.licenses.connection') ?: null);
            $exists = $schema->hasTable($table);

            $this->result(
                'tabela '.$table,
                $exists,
                $exists ? IdosellLicense::count().' licencji' : 'uruchom php artisan migrate',
            );
        } catch (Throwable $e) {
            $this->result('tabela '.$table, false, 'brak połączenia z bazą: '.$e::class);
        }
    }

    private function checkKeyset(): void
    {
        try {
            $response = Http::timeout(5)->get((string) config('idosell.apps.keyset_url'));
            $iv = trim($response->body());

            // IV dla AES-256-CBC ma dokładnie 16 bajtów — inna długość oznacza, że
            // dostaliśmy stronę błędu zamiast wektora.
            $this->result(
                'keyset (IV)',
                $response->successful() && strlen($iv) === 16,
                $response->successful() ? 'długość IV: '.strlen($iv) : 'HTTP '.$response->status(),
            );
        } catch (Throwable $e) {
            $this->result('keyset (IV)', false, $e::class);
        }
    }

    private function checkAppsApi(AppsApiClient $apps): void
    {
        try {
            $response = $apps->licenses();

            $this->result(
                'Apps API /application/license',
                true,
                'licencji w odpowiedzi: '.count((array) ($response['licenses'] ?? $response['results'] ?? [])),
            );
        } catch (Throwable $e) {
            $this->result('Apps API /application/license', false, $e->getMessage());
        }
    }

    private function result(string $label, bool $ok, string $detail): void
    {
        $this->results[] = compact('label', 'ok', 'detail');

        $this->components->twoColumnDetail(
            $label,
            ($ok ? '<fg=green>OK</>' : '<fg=red>BŁĄD</>').' <fg=gray>'.$detail.'</>',
        );
    }
}
