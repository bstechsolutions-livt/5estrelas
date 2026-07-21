<?php

namespace App\Console\Commands;

use App\Services\Senior\FornecedoresSyncService;
use Illuminate\Console\Command;

class SyncSeniorFornecedores extends Command
{
    protected $signature = 'senior:sync-fornecedores
        {--full : Sincroniza o catálogo completo via paginação (bootstrap/noturno)}
        {--missing : Sincroniza só fornecedores dos títulos sem cache (default)}
        {--purge-unresolved : Remove stubs unresolved do cache antes do sync}
        {--enrich-only : Só reenriquece supplier_name a partir do cache/descrição}
        {--max= : Teto de lookups Exportar neste ciclo (default: config)}
        {--scheduled : Marca a execução como agendada}';

    protected $description = 'Sincroniza fornecedores da Senior (cad_fornecedor) e enriquece títulos';

    public function handle(): int
    {
        $svc = FornecedoresSyncService::make();
        $full = (bool) $this->option('full');
        $enrichOnly = (bool) $this->option('enrich-only');
        $trigger = $this->option('scheduled') ? 'agendado' : 'manual';

        if ($this->option('purge-unresolved')) {
            $purged = $svc->purgeUnresolvedStubs();
            $this->info("Stubs unresolved removidos: {$purged}.");
        }

        if ($enrichOnly) {
            $enriched = $svc->enrichPayables();
            $enrichedDesc = $svc->enrichFromDescriptions();
            $this->info(sprintf(
                'Enrich fornecedores: %d via cache, %d via descrição. Faltantes=%d, stubs unresolved=%d.',
                $enriched,
                $enrichedDesc,
                $svc->countMissingSuppliers(),
                $svc->countUnresolvedStubs(),
            ));

            return self::SUCCESS;
        }

        $maxOpt = $this->option('max');
        $maxLookups = $maxOpt !== null && $maxOpt !== ''
            ? max(1, (int) $maxOpt)
            : (int) config('senior.fornecedor_max_lookups_per_sync', 40);

        $r = $full
            ? $svc->run($trigger, true)
            : $svc->syncMissingFromPayables($trigger, maxLookups: $maxLookups);

        $mode = $full ? 'full' : 'delta';
        $this->info(sprintf(
            'Sync fornecedores [%s/%s]: %d lookups, %d inseridos, %d atualizados, %d erros, %d enriquecidos (cache), %d enriquecidos (descrição). Faltantes=%d, stubs unresolved=%d.',
            $r['status'],
            $mode,
            $r['looked_up'] ?? 0,
            $r['inserted'],
            $r['updated'],
            $r['errors'],
            $r['enriched'],
            $r['enriched_desc'] ?? 0,
            $svc->countMissingSuppliers(),
            $svc->countUnresolvedStubs(),
        ));

        if ($r['status'] === 'failed') {
            $this->error('Falha: ' . ($r['message'] ?? 'desconhecida'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
