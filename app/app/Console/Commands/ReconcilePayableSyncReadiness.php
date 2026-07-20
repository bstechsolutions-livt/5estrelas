<?php

namespace App\Console\Commands;

use App\Models\Payable;
use App\Services\Senior\FornecedoresSyncService;
use App\Services\Senior\PayableLauncherSyncService;
use App\Services\Senior\PayablesSyncService;
use Illuminate\Console\Command;

class ReconcilePayableSyncReadiness extends Command
{
    protected $signature = 'payables:reconcile-sync-readiness
        {--launcher-max=80 : Máximo de consultas UsuGer na Senior}
        {--supplier-max=100 : Máximo de consultas de fornecedor na Senior}
        {--only-awaiting : Só processa títulos em aguardando sincronização}';

    protected $description = 'Enriquece lançador/fornecedor e reclassifica títulos abertos (pendente vs aguardando sincronização)';

    public function handle(): int
    {
        if ($this->option('only-awaiting')) {
            return $this->reconcileAwaitingOnly();
        }

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

    private function reconcileAwaitingOnly(): int
    {
        $ids = PayablesSyncService::make()->awaitingSyncPayableIds();
        if ($ids === []) {
            $this->info('Nenhum título em aguardando sincronização.');

            return self::SUCCESS;
        }

        $before = count($ids);
        $cacheEnriched = FornecedoresSyncService::make()->enrichPayables($ids);
        PayablesSyncService::make()->resolveDepartmentsAfterSync($ids);
        $ids = PayablesSyncService::make()->awaitingSyncPayableIds();
        if ($ids === []) {
            $this->info("Cache: {$cacheEnriched} nome(s) materializado(s). Fila esvaziada.");

            return self::SUCCESS;
        }
        $before = count($ids);

        $launcher = PayableLauncherSyncService::make()->enrichByPayableIds(
            $ids,
            maxLookups: (int) $this->option('launcher-max'),
            trigger: 'reconcile-awaiting',
        );

        $sup = FornecedoresSyncService::make()->syncMissingFromPayables(
            'reconcile-awaiting',
            maxLookups: (int) $this->option('supplier-max'),
            prioritizePayableIds: $ids,
        );

        $changed = PayablesSyncService::make()->resolveDepartmentsAfterSync($ids);
        $after = Payable::query()->where('status', Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO)->count();
        $liberados = max(0, $before - $after);

        $this->info(sprintf(
            'Aguardando sync: %d título(s) na fila → %d liberado(s), %d restante(s). UsuGer: %d consultas (%d ok). Fornecedor: %d consultas (%d nomes). Reclassificados: %d.',
            $before,
            $liberados,
            $after,
            (int) ($launcher['looked_up'] ?? 0),
            (int) ($launcher['updated'] ?? 0),
            (int) ($sup['looked_up'] ?? 0),
            (int) (($sup['enriched'] ?? 0) + ($sup['enriched_desc'] ?? 0)),
            $changed,
        ));

        return self::SUCCESS;
    }
}
