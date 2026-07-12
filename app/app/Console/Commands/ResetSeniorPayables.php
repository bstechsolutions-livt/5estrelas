<?php

namespace App\Console\Commands;

use App\Models\Payable;
use App\Models\PayableSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remove títulos CP importados da Senior para re-sync limpo (ex.: migração sweep → bulk).
 */
class ResetSeniorPayables extends Command
{
    protected $signature = 'senior:reset-payables
        {--emp= : codEmp específico (ex.: 2). Omitir = todas as empresas}
        {--force : Confirma sem prompt interativo}';

    protected $description = 'Remove títulos CP da Senior do banco para reimportação limpa';

    public function handle(): int
    {
        $codEmp = $this->option('emp') !== null ? (int) $this->option('emp') : null;

        $query = Payable::query()->whereNotNull('senior_id');
        if ($codEmp !== null && $codEmp > 0) {
            $query->where('codemp', $codEmp);
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('Nenhum título Senior para remover.');

            return self::SUCCESS;
        }

        $scope = $codEmp ? "empresa {$codEmp}" : 'todas as empresas';
        if (!$this->option('force') && !$this->confirm("Remover {$count} título(s) Senior ({$scope})? Workflow/borderôs podem perder vínculo.")) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($query) {
            $ids = $query->pluck('id');
            DB::table('payable_rateios')->whereIn('payable_id', $ids)->delete();
            Payable::whereIn('id', $ids)->delete();
        });

        PayableSyncRun::query()->delete();

        $this->info("Removidos {$count} título(s). payable_sync_runs zerado.");

        return self::SUCCESS;
    }
}
