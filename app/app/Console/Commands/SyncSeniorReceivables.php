<?php

namespace App\Console\Commands;

use App\Models\ReceivableSyncRun;
use App\Services\Senior\ReceivablesSyncService;
use Illuminate\Console\Command;

class SyncSeniorReceivables extends Command
{
    protected $signature = 'senior:sync-receivables
        {--full : Executa em modo completo (Full_Sync) em vez de incremental}
        {--from= : Data inicial da janela de vencimento (Y-m-d)}
        {--to= : Data final da janela de vencimento (Y-m-d)}
        {--scheduled : Marca a execução como agendada (default: manual)}';

    protected $description = 'Sincroniza os títulos a receber da Senior (Contas a Receber)';

    public function handle(): int
    {
        $mode = $this->option('full') ? ReceivableSyncRun::MODE_FULL : ReceivableSyncRun::MODE_INCREMENTAL;
        $trigger = $this->option('scheduled') ? ReceivableSyncRun::TRIGGER_SCHEDULED : ReceivableSyncRun::TRIGGER_MANUAL;

        $from = $this->option('from') ? \Carbon\Carbon::parse($this->option('from')) : null;
        $to = $this->option('to') ? \Carbon\Carbon::parse($this->option('to')) : null;

        $run = ReceivablesSyncService::make()->run($mode, $trigger, $from, $to);

        $this->info(sprintf(
            'Sync %s [%s/%s]: %d inseridos, %d atualizados, %d ausentes.',
            $run->status,
            $run->environment,
            $run->mode,
            $run->inserted_count,
            $run->updated_count,
            $run->missing_count,
        ));

        if ($run->status === ReceivableSyncRun::STATUS_FAILED) {
            $this->error('Falha: ' . $run->error_message);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
