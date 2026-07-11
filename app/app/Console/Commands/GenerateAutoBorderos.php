<?php

namespace App\Console\Commands;

use App\Services\BorderoAutoGroupService;
use Illuminate\Console\Command;

class GenerateAutoBorderos extends Command
{
    protected $signature = 'borderos:auto-generate {--scheduled : Execução agendada (cron)}';

    protected $description = 'Gera borderôs automáticos em rascunho conforme as regras ativas';

    public function handle(BorderoAutoGroupService $grouper): int
    {
        $result = $grouper->runActiveRulesForCron();

        if ($result['created'] === 0) {
            $this->info('Borderô automático: nenhuma regra ativa ou nenhum título elegível.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Borderô automático: %d borderô(s) criado(s) em rascunho (%d regra(s) executada(s)).',
            $result['created'],
            count($result['rules']),
        ));

        return self::SUCCESS;
    }
}
