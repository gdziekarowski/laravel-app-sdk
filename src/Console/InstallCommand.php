<?php

namespace Idosell\LaravelAppSdk\Console;

use Illuminate\Console\Command;

/**
 * Jednorazowa instalacja SDK w aplikacji: publikacja configu (opcjonalnie migracji
 * i bazy wiedzy) oraz wypisanie adresów do wklejenia w panelu dewelopera.
 */
class InstallCommand extends Command
{
    protected $signature = 'idosell:install
                            {--migrations : Opublikuj migracje do database/migrations zamiast ładować je z paczki}
                            {--skills : Opublikuj bazę wiedzy o IdoSell do .claude/skills}
                            {--force : Nadpisz istniejące pliki}';

    protected $description = 'Instaluje IdoSell App SDK: publikuje config i pokazuje adresy webhooków';

    public function handle(): int
    {
        $this->components->info('Instalacja IdoSell App SDK');

        $this->callSilently('vendor:publish', array_filter([
            '--tag' => 'idosell-config',
            '--force' => $this->option('force'),
        ]));
        $this->components->task('config/idosell.php', fn (): bool => true);

        if ($this->option('migrations')) {
            $this->callSilently('vendor:publish', array_filter([
                '--tag' => 'idosell-migrations',
                '--force' => $this->option('force'),
            ]));
            $this->components->task('database/migrations (ustaw IDOSELL_RUN_MIGRATIONS=false)', fn (): bool => true);
        }

        if ($this->option('skills')) {
            $this->callSilently('vendor:publish', array_filter([
                '--tag' => 'idosell-skills',
                '--force' => $this->option('force'),
            ]));
            $this->components->task('.claude/skills (baza wiedzy o IdoSell)', fn (): bool => true);
        }

        $this->newLine();
        $this->showEnvChecklist();
        $this->newLine();
        $this->showWebhookUrls();

        return self::SUCCESS;
    }

    private function showEnvChecklist(): void
    {
        $this->components->twoColumnDetail('<fg=yellow>1. Uzupełnij .env</>', '');

        foreach ([
            'IDOSELL_APPLICATION_ID' => config('idosell.apps.application_id'),
            'IDOSELL_DEVELOPER' => config('idosell.apps.developer'),
            'IDOSELL_APPLICATION_KEY' => config('idosell.apps.application_key'),
        ] as $key => $value) {
            $this->components->twoColumnDetail(
                '  '.$key,
                $value ? '<fg=green>ustawione</>' : '<fg=red>brak</>',
            );
        }

        $this->components->twoColumnDetail(
            '  IDOSELL_LAUNCH_ROUTE',
            config('idosell.launch.route') ?: '<fg=red>brak — nazwa trasy panelu aplikacji</>',
        );
    }

    private function showWebhookUrls(): void
    {
        $this->components->twoColumnDetail('<fg=yellow>2. Wpisz w panelu apps.idosell.com → Technical data</>', '');

        if (!config('idosell.routes.enabled', true)) {
            $this->components->warn('Trasy paczki są wyłączone (idosell.routes.enabled=false) — użyj własnych.');

            return;
        }

        $base = rtrim((string) config('app.url'), '/').'/'.trim((string) config('idosell.routes.prefix'), '/');

        $this->components->twoColumnDetail('  Link to working application', $base.'/launch');
        $this->components->twoColumnDetail('  Webhook address to enable the application', $base.'/new-license');
        $this->components->twoColumnDetail('  Webhook address to disable the application', $base.'/remove-license');
        $this->components->twoColumnDetail('  Webhook address to modify application licence', $base.'/new-license');

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=yellow>3. Sprawdź konfigurację</>',
            'php artisan idosell:doctor',
        );
    }
}
