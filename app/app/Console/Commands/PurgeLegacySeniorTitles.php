<?php

namespace App\Console\Commands;

use App\Models\Payable;
use App\Models\Receivable;
use App\Services\AuditLogger;
use App\Support\SeniorDueDatePolicy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeLegacySeniorTitles extends Command
{
    protected $signature = 'senior:purge-legacy-titles
        {--before= : Data de corte (vencimento). Default: senior.min_due_date}
        {--cp : Apenas Contas a Pagar}
        {--cr : Apenas Contas a Receber}
        {--dry-run : Apenas exibe contagens}
        {--force : Confirma sem prompt}';

    protected $description = 'Remove títulos Senior com vencimento anterior ao corte (padrão: 2026-01-01)';

    public function handle(): int
    {
        $before = Carbon::parse($this->option('before') ?: SeniorDueDatePolicy::minDueDate()->toDateString())->startOfDay();
        $onlyCp = (bool) $this->option('cp');
        $onlyCr = (bool) $this->option('cr');
        $dryRun = (bool) $this->option('dry-run');

        $doCp = $onlyCp || (!$onlyCp && !$onlyCr);
        $doCr = $onlyCr || (!$onlyCp && !$onlyCr);

        $cpCount = $doCp ? $this->legacyPayablesQuery($before)->count() : 0;
        $crCount = $doCr ? $this->legacyReceivablesQuery($before)->count() : 0;

        $this->info("Corte: vencimento anterior a {$before->toDateString()}");
        if ($doCp) {
            $this->line("  CP a remover: {$cpCount}");
        }
        if ($doCr) {
            $this->line("  CR a remover: {$crCount}");
        }

        if ($cpCount + $crCount === 0) {
            $this->info('Nada a remover.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry-run — nenhum registro apagado.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Confirmar exclusão?')) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $removedCp = 0;
        $removedCr = 0;

        DB::transaction(function () use ($before, $doCp, $doCr, &$removedCp, &$removedCr) {
            if ($doCp) {
                $ids = $this->legacyPayablesQuery($before)->pluck('id');
                $removedCp = $ids->count();
                if ($removedCp > 0) {
                    DB::table('approval_steps')->whereIn('payable_id', $ids)->delete();
                    Payable::whereIn('id', $ids)->delete();
                }
            }

            if ($doCr) {
                $ids = $this->legacyReceivablesQuery($before)->pluck('id');
                $removedCr = $ids->count();
                if ($removedCr > 0) {
                    Receivable::whereIn('id', $ids)->delete();
                }
            }
        });

        AuditLogger::log(
            event: 'senior.purge_legacy_titles',
            module: 'financeiro',
            description: "Removidos {$removedCp} CP e {$removedCr} CR com vencimento antes de {$before->toDateString()}",
            metadata: ['before' => $before->toDateString(), 'cp' => $removedCp, 'cr' => $removedCr],
        );

        $this->info("Removidos {$removedCp} título(s) CP e {$removedCr} título(s) CR.");

        return self::SUCCESS;
    }

    private function legacyPayablesQuery(Carbon $before)
    {
        return Payable::query()
            ->where(function ($q) use ($before) {
                $q->where('due_date', '<', $before)
                    ->orWhere(function ($q2) use ($before) {
                        $q2->whereNull('due_date')
                            ->whereNotNull('issue_date')
                            ->where('issue_date', '<', $before);
                    });
            });
    }

    private function legacyReceivablesQuery(Carbon $before)
    {
        return Receivable::query()
            ->where(function ($q) use ($before) {
                $q->where('due_date', '<', $before)
                    ->orWhere(function ($q2) use ($before) {
                        $q2->whereNull('due_date')
                            ->whereNotNull('issue_date')
                            ->where('issue_date', '<', $before);
                    });
            });
    }
}
