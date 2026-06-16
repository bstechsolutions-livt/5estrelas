<?php

namespace App\Console\Commands;

use App\Models\PayableSyncRun;
use App\Services\Senior\PayablesSyncService;
use Illuminate\Console\Command;

/**
 * Dispara o Payables_Sync (spec senior-contas-pagar-sync).
 * Usado tanto pelo agendador (trigger=agendado) quanto manualmente.
 */
class SyncSeniorPayables extends Command
{
    protected $signature = 'senior:sync-payables
        {--full : Executa em modo completo (Full_Sync) em vez de incremental}
        {--scheduled : Marca a execução como agendada (default: manual)}';

    protected $description = 'Sincroniza os títulos a pagar da Senior (Contas a Pagar)';

    public function handle(): int
    {
        $mode = $this->option('full') ? PayableSyncRun::MODE_FULL : PayableSyncRun::MODE_INCREMENTAL;
        $trigger = $this->option('scheduled') ? PayableSyncRun::TRIGGER_SCHEDULED : PayableSyncRun::TRIGGER_MANUAL;

        $run = PayablesSyncService::make()->run($mode, $trigger);

        $this->info(sprintf(
            'Sync %s [%s/%s]: %d inseridos, %d atualizados, %d ausentes.',
            $run->status,
            $run->environment,
            $run->mode,
            $run->inserted_count,
            $run->updated_count,
            $run->missing_count,
        ));

        if ($run->status === PayableSyncRun::STATUS_FAILED) {
            $this->error('Falha: ' . $run->error_message);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
