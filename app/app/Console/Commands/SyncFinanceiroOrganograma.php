<?php

namespace App\Console\Commands;

use Database\Seeders\ApprovalTrailSeeder;
use Database\Seeders\DepartmentHierarchySeeder;
use Illuminate\Console\Command;

class SyncFinanceiroOrganograma extends Command
{
    protected $signature = 'financeiro:sync-organograma
                            {--trails : Recria também as trilhas de aprovação (apaga e recria)}
                            {--force : Sem confirmação interativa}';

    protected $description = 'Sincroniza departamentos e trilhas conforme organograma de aprovação v3.0';

    public function handle(): int
    {
        $this->info('Sincronizando departamentos...');
        (new DepartmentHierarchySeeder)->setCommand($this)->run();

        if ($this->option('trails')) {
            if (! $this->option('force') && ! $this->confirm('Isso apaga e recria TODAS as trilhas de aprovação. Continuar?')) {
                $this->warn('Trilhas não alteradas.');

                return self::SUCCESS;
            }
            $this->info('Sincronizando trilhas...');
            (new ApprovalTrailSeeder)->setCommand($this)->run();
        }

        $this->info('Organograma sincronizado.');

        return self::SUCCESS;
    }
}
