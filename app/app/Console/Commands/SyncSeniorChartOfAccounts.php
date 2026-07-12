<?php

namespace App\Console\Commands;

use App\Services\Senior\ChartOfAccountsSyncService;
use Illuminate\Console\Command;

class SyncSeniorChartOfAccounts extends Command
{
    protected $signature = 'senior:sync-chart-of-accounts';

    protected $description = 'Atualiza o plano de contas (interim) a partir de ctaFin/codCcu em CP e CR';

    public function handle(): int
    {
        $result = (new ChartOfAccountsSyncService())->run();

        $this->info(sprintf(
            'Plano de contas: %d distintos, %d inseridos, %d atualizados.',
            $result['total_distinct'],
            $result['inserted'],
            $result['updated'],
        ));

        return self::SUCCESS;
    }
}
