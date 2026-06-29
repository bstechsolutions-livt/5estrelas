<?php

namespace App\Console\Commands;

use App\Services\Senior\FiliaisSyncService;
use Illuminate\Console\Command;

/**
 * Sincroniza as filiais/empresas do grupo a partir da Senior (cad_filial).
 * Read-only. Em modo desabilitado (senior.enabled=false) conclui sem tocar nos dados.
 */
class SyncSeniorFiliais extends Command
{
    protected $signature = 'senior:sync-filiais {--scheduled : Marca a execução como agendada (default: manual)}';

    protected $description = 'Sincroniza as filiais/empresas do grupo a partir da Senior (cad_filial)';

    public function handle(): int
    {
        $trigger = $this->option('scheduled') ? 'agendado' : 'manual';
        $r = FiliaisSyncService::make()->run($trigger);

        $this->info(sprintf(
            'Sync filiais [%s]: %d inseridas, %d atualizadas, %d erros.',
            $r['status'],
            $r['inserted'],
            $r['updated'],
            $r['errors'],
        ));

        if ($r['status'] === 'failed') {
            $this->error('Falha: ' . ($r['message'] ?? 'desconhecida'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
