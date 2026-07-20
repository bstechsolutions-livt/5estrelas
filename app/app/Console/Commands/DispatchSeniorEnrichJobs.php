<?php

namespace App\Console\Commands;

use App\Services\Senior\PayableEnrichQueueDispatcher;
use Illuminate\Console\Command;

class DispatchSeniorEnrichJobs extends Command
{
    protected $signature = 'senior:dispatch-enrich-jobs
        {--scheduled : Execução agendada (cron)}';

    protected $description = 'Despacha jobs de enrich Senior (UsuGer + fornecedor + prontidão) nas filas dedicadas';

    public function handle(): int
    {
        if (! config('senior.enrich_use_queue', false)) {
            $this->warn('SENIOR_ENRICH_USE_QUEUE=false — nada despachado.');

            return self::SUCCESS;
        }

        $trigger = $this->option('scheduled') ? 'cron' : 'manual';
        $result = PayableEnrichQueueDispatcher::make()->dispatchCron($trigger);

        $this->info(sprintf(
            'Enrich queue [%s]: %d título(s), %d job(s) lançador, %d job(s) fornecedor, %d job(s) prontidão.',
            $trigger,
            $result['payable_ids'],
            $result['launcher_jobs'],
            $result['supplier_jobs'],
            $result['readiness_jobs'],
        ));

        return self::SUCCESS;
    }
}
