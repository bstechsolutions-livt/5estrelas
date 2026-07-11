<?php

namespace App\Console\Commands;

use App\Models\BorderoAutoConfig;
use App\Services\BorderoAutoGroupService;
use Illuminate\Console\Command;

class GenerateAutoBorderos extends Command
{
    protected $signature = 'borderos:auto-generate {--scheduled : Execução agendada (cron)}';

    protected $description = 'Gera borderôs automáticos em rascunho conforme a configuração salva';

    public function handle(BorderoAutoGroupService $grouper): int
    {
        $config = BorderoAutoConfig::current();

        if (! $config->cron_enabled) {
            $this->info('Borderô automático: cron desligado na configuração — nada a fazer.');

            return self::SUCCESS;
        }

        $result = $grouper->generateAllFromConfig($config);

        if (! empty($result['skipped'])) {
            $this->info('Borderô automático: ignorado (cron desligado).');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Borderô automático: %d borderô(s) criado(s) em rascunho.',
            $result['created'],
        ));

        return self::SUCCESS;
    }
}
