<?php

namespace App\Console\Commands;

use App\Services\Senior\PayableLauncherSyncService;
use Illuminate\Console\Command;

class SyncSeniorPayableLaunchers extends Command
{
    protected $signature = 'senior:enrich-payable-launchers
        {--cod-emp= : Filtra empresa Senior (codEmp)}
        {--cod-fil= : Filtra filial Senior (codFil)}
        {--max= : Limite de consultas Exportar E (após o bulk)}
        {--scheduled : Marca a execução como agendada}';

    protected $description = 'Preenche senior_cod_usu dos títulos via UsuGer (prj.contaspagar)';

    public function handle(): int
    {
        $codEmp = $this->option('cod-emp') !== null && $this->option('cod-emp') !== ''
            ? (int) $this->option('cod-emp')
            : null;
        $codFil = $this->option('cod-fil') !== null && $this->option('cod-fil') !== ''
            ? (int) $this->option('cod-fil')
            : null;
        $max = $this->option('max') !== null && $this->option('max') !== ''
            ? (int) $this->option('max')
            : null;
        $trigger = $this->option('scheduled') ? 'agendado' : 'manual';

        $r = PayableLauncherSyncService::make()->run($codEmp, $codFil, $max, $trigger);

        $this->info(sprintf(
            'Enrich lançadores [%s]: bulk=%d, lookups=%d, updated=%d, errors=%d, skipped=%d.',
            $r['status'],
            $r['bulk_matched'],
            $r['looked_up'],
            $r['updated'],
            $r['errors'],
            $r['skipped'],
        ));

        if (($r['message'] ?? null) && $r['status'] !== 'ok') {
            $this->warn($r['message']);
        }

        return $r['status'] === 'ok' || $r['status'] === 'skipped'
            ? self::SUCCESS
            : self::FAILURE;
    }
}
