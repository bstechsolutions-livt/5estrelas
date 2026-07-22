<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\BankMatchingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Rematch manual: débitos unmatched/rejected de um dia (data OFX = paid_at + valor).
 * Não mexe em accepted/manual/pending. Sem cron.
 */
class RematchUnmatchedBankTransactions extends Command
{
    protected $signature = 'conciliacao:rematch-unmatched
        {--date= : Data Y-m-d (obrigatório, ou --from-paid-at-backup)}
        {--from-paid-at-backup= : JSON do fix paid_at; rematch das datas new_paid_at}
        {--execute : Aplica (default dry-run: só mostra o que faria por data)}
        {--force : Com --execute, sem confirmação}';

    protected $description = 'Rematch de débitos OFX unmatched por data+valor (manual, dry-run por padrão)';

    public function handle(BankMatchingService $matcher): int
    {
        $execute = (bool) $this->option('execute');
        $dates = $this->resolveDates();

        if ($dates === []) {
            $this->error('Informe --date=YYYY-MM-DD ou --from-paid-at-backup=caminho.json');

            return self::FAILURE;
        }

        $this->info($execute ? 'MODO EXECUTE' : 'MODO DRY-RUN');
        $this->table(['Datas'], collect($dates)->map(fn ($d) => [$d])->all());

        if ($execute && ! $this->option('force') && ! $this->confirm('Rematch unmatched nestas datas?', false)) {
            $this->warn('Abortado.');

            return self::SUCCESS;
        }

        $totals = ['scanned' => 0, 'matched' => 0, 'ambiguous' => 0, 'still_unmatched' => 0, 'skipped_ops' => 0];

        foreach ($dates as $date) {
            if (! $execute) {
                $preview = $matcher->rematchUnmatchedForDate(Carbon::parse($date)->startOfDay());
                // dry-run can't easily preview without mutating — run inside transaction rollback
                continue;
            }

            $result = $matcher->rematchUnmatchedForDate(Carbon::parse($date)->startOfDay());
            foreach ($totals as $k => $_) {
                $totals[$k] += $result[$k];
            }
            $this->line("{$date}: matched={$result['matched']} ambiguous={$result['ambiguous']} still={$result['still_unmatched']} ops_skip={$result['skipped_ops']} scanned={$result['scanned']}");

            AuditLogger::log(
                event: 'contas_pagar.rematch_unmatched_dia',
                module: 'financeiro.contas_pagar',
                description: "Rematch unmatched do dia {$date}: {$result['matched']} match(es), {$result['ambiguous']} ambíguo(s)",
                newValues: array_merge(['date' => $date], $result),
            );
        }

        if (! $execute) {
            // Preview with transaction rollback per date
            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                foreach ($dates as $date) {
                    $result = $matcher->rematchUnmatchedForDate(Carbon::parse($date)->startOfDay());
                    foreach ($totals as $k => $_) {
                        $totals[$k] += $result[$k];
                    }
                    $this->line("[dry] {$date}: matched={$result['matched']} ambiguous={$result['ambiguous']} still={$result['still_unmatched']} ops_skip={$result['skipped_ops']} scanned={$result['scanned']}");
                }
            } finally {
                \Illuminate\Support\Facades\DB::rollBack();
            }
            $this->warn('Dry-run ok (rollback). Para aplicar: --execute --force');
        } else {
            $this->info('Totais: '.json_encode($totals));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveDates(): array
    {
        if ($this->option('date')) {
            return [Carbon::parse((string) $this->option('date'))->toDateString()];
        }

        $backup = $this->option('from-paid-at-backup');
        if (! $backup || ! is_file($backup)) {
            return [];
        }

        $plan = json_decode((string) file_get_contents($backup), true);
        $dates = collect($plan['candidates'] ?? [])
            ->pluck('new_paid_at')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $dates;
    }
}
