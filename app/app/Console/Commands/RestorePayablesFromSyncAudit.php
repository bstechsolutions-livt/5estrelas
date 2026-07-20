<?php

namespace App\Console\Commands;

use App\Models\Payable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestorePayablesFromSyncAudit extends Command
{
    protected $signature = 'payables:restore-from-sync-audit
        {--since=2026-07-20 : Data mínima do audit (Y-m-d)}
        {--apply : Aplica a restauração (sem isso, só simula)}';

    protected $description = 'Restaura status de títulos rebaixados para aguardando sync a partir do audit log';

    /** Status que nunca deveriam ter ido para aguardando sync. */
    private const PROTECTED_STATUSES = [
        'aguardando_aprovacao',
        'aprovado',
        'pago',
        'aguardando_conciliacao',
        'conciliado',
        'reprovado',
        'divergente',
        'encerrado',
    ];

    public function handle(): int
    {
        $since = (string) $this->option('since');
        $apply = (bool) $this->option('apply');

        $logs = DB::table('audit_logs')
            ->where('auditable_type', Payable::class)
            ->where('created_at', '>=', $since . ' 00:00:00')
            ->where('new_values', 'like', '%aguardando_vinculo_departamento%')
            ->orderByDesc('id')
            ->get(['id', 'auditable_id', 'old_values', 'new_values', 'created_at']);

        $byPayable = [];
        foreach ($logs as $log) {
            $payableId = (int) $log->auditable_id;
            if ($payableId <= 0 || isset($byPayable[$payableId])) {
                continue;
            }

            $old = json_decode((string) $log->old_values, true) ?: [];
            $previousStatus = (string) ($old['status'] ?? '');

            if (! in_array($previousStatus, self::PROTECTED_STATUSES, true)) {
                continue;
            }

            $byPayable[$payableId] = [
                'audit_id' => (int) $log->id,
                'old' => $old,
                'at' => (string) $log->created_at,
            ];
        }

        if ($byPayable === []) {
            $this->info('Nenhum título elegível para restauração.');

            return self::SUCCESS;
        }

        $counts = [];
        $restored = 0;
        $skipped = 0;

        foreach ($byPayable as $payableId => $info) {
            $payable = Payable::query()->find($payableId);
            if ($payable === null) {
                $skipped++;
                continue;
            }

            if ($payable->status !== Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO) {
                $skipped++;
                continue;
            }

            $previousStatus = (string) ($info['old']['status'] ?? '');
            $counts[$previousStatus] = ($counts[$previousStatus] ?? 0) + 1;

            $this->line(sprintf(
                '  #%d %s: %s → %s (audit #%d %s)',
                $payable->id,
                $payable->title_number,
                $payable->status,
                $previousStatus,
                $info['audit_id'],
                $info['at'],
            ));

            if ($apply) {
                $updates = ['status' => $previousStatus];
                foreach (['department_id', 'bordero_id'] as $field) {
                    if (array_key_exists($field, $info['old'])) {
                        $updates[$field] = $info['old'][$field];
                    }
                }
                $payable->update($updates);
                $restored++;
            }
        }

        $this->newLine();
        $this->info('Por status anterior:');
        foreach ($counts as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        if ($apply) {
            $this->info("Restaurados: {$restored}. Ignorados (já corrigidos/ausentes): {$skipped}.");
        } else {
            $this->comment(sprintf(
                'Simulação: %d título(s) seriam restaurados (%d ignorados). Use --apply.',
                count($counts) > 0 ? array_sum($counts) : 0,
                $skipped,
            ));
        }

        return self::SUCCESS;
    }
}
