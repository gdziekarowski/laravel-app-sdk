<?php

namespace Idosell\LaravelAppSdk\Console;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Console\Command;

/**
 * Podgląd zainstalowanych licencji.
 *
 * Nie wypisujemy `api_key` ani `api_license` — to sekrety, a wynik komendy trafia
 * do historii terminala i logów CI.
 */
class LicensesCommand extends Command
{
    protected $signature = 'idosell:licenses
                            {--active : Tylko aktywne licencje}
                            {--client= : Zawęź do konta sprzedawcy (client_id)}';

    protected $description = 'Wypisuje licencje aplikacji zapisane w bazie (bez sekretów)';

    public function handle(): int
    {
        $query = IdosellLicense::query()->orderBy('client_id');

        if ($this->option('active')) {
            $query->active();
        }

        if ($clientId = $this->option('client')) {
            $query->forClient((int) $clientId);
        }

        $licenses = $query->get();

        if ($licenses->isEmpty()) {
            $this->components->warn('Brak licencji spełniających kryteria.');

            return self::SUCCESS;
        }

        // Wąska tabela celowo: przy większej liczbie kolumn konsola zawija komórki
        // i wynik przestaje być czytelny (oraz przeszukiwalny) na standardowych 80 znakach.
        $this->table(
            ['client', 'app', 'panel', 'auth', 'aktywna', 'instal.', 'sklepy', 'od'],
            $licenses->map(fn (IdosellLicense $license): array => [
                $license->client_id,
                $license->application_id,
                $license->domain() ?? '—',
                $license->authorization_type,
                $license->active ? 'tak' : 'nie',
                $license->installation_confirmed ? 'tak' : 'nie',
                count($license->shops()),
                $license->created_at?->format('Y-m-d') ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
