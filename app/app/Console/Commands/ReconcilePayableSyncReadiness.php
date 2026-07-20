<?php

namespace App\Console\Commands;

use App\Services\Senior\PayablesSyncService;
use Illuminate\Console\Command;

class ReconcilePayableSyncReadiness extends Command
{
    protected $signature = 'payables:reconcile-sync-readiness
        {--launcher-max=400 : Máximo de consultas UsuGer na Senior}
        {--supplier-max=500 : Máximo de consultas de fornecedor na Senior}';

    protected $description = 'Enriquece lançador/fornecedor e reclassifica títulos abertos (pendente vs aguardando sincronização)';

    public function handle(): int
    {
        $result = PayablesSyncService::make()->reconcileOpenSyncReadiness(
            supplierMaxLookups: (int) $this->option('supplier-max'),
            launcherMaxLookups: (int) $this->option('launcher-max'),
        );

        $this->info(sprintf(
            'Reconcile: %d UsuGer consultados (%d ok), %d fornecedores consultados (%d nomes), %d título(s) reclassificado(s), %d movido(s) para aguardando sync.',
            $result['launchers_looked_up'],
            $result['launchers_updated'],
            $result['suppliers_looked_up'],
            $result['suppliers_enriched'],
            $result['readiness_changed'],
            $result['moved_to_aguardando'],
        ));

        return self::SUCCESS;
    }
}
