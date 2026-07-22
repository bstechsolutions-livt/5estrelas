<?php

namespace App\Console\Commands;

use App\Models\Payable;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Correção pontual (manual): paid_at veio da data de aprovação do Gestor
 * em vez de receipt.paymentDate do comprovante.
 *
 * Não altera status. Não agenda cron. Default = dry-run.
 */
class FixPayablePaidAtFromGestorReceipt extends Command
{
    protected $signature = 'gestor:fix-paid-at-from-receipt
        {--source= : JSON do dump Gestor (default: storage/app/gestor-migration-rerun-execute.json)}
        {--execute : Grava paid_at (sem isso = só dry-run)}
        {--payable= : Limita a um payable_id}
        {--limit=0 : Máximo de updates (0 = sem limite)}
        {--only-approval-mismatch : Só corrige quando paid_at atual = data do approved (padrão: ligado)}
        {--all-mismatches : Corrige qualquer divergência paid_at ≠ paymentDate}
        {--backup= : Caminho do backup JSON (default: storage/app/backups/paid_at_fix_{timestamp}.json)}
        {--force : Com --execute, não pede confirmação interativa}';

    protected $description = 'Corrige paid_at a partir de receipt.paymentDate do dump Gestor (manual, dry-run por padrão)';

    public function handle(): int
    {
        $source = $this->option('source')
            ?: storage_path('app/gestor-migration-rerun-execute.json');

        if (! is_file($source)) {
            $this->error("Arquivo não encontrado: {$source}");

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $onlyApprovalFallback = ! (bool) $this->option('all-mismatches');
        $payableFilter = $this->option('payable') !== null && $this->option('payable') !== ''
            ? (int) $this->option('payable')
            : null;
        $limit = (int) $this->option('limit');

        $this->info($execute ? 'MODO EXECUTE — vai gravar paid_at' : 'MODO DRY-RUN — não grava nada');
        $this->line('Fonte: '.$source);
        $this->line('Filtro: '.($onlyApprovalFallback
            ? 'só quando paid_at == data do approved (fallback bugado)'
            : 'qualquer divergência paid_at ≠ paymentDate'));

        $raw = file_get_contents($source);
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $this->error('JSON inválido.');

            return self::FAILURE;
        }

        $byPayable = $this->collectPaymentDates($data);
        $this->info('Documentos Gestor com paymentDate + payable_id: '.count($byPayable));

        $candidates = [];
        foreach ($byPayable as $payableId => $info) {
            if ($payableFilter !== null && $payableId !== $payableFilter) {
                continue;
            }

            $payable = Payable::query()->find($payableId);
            if (! $payable) {
                continue;
            }

            $current = $payable->paid_at?->toDateString();
            $paymentDate = $info['payment_date'];
            $approvedDate = $info['approved_date'];

            if ($current === $paymentDate) {
                continue;
            }

            if ($onlyApprovalFallback) {
                // Só corrige o padrão do bug: paid_at veio do approved
                if ($approvedDate === null || $current !== $approvedDate) {
                    continue;
                }
            }

            $candidates[] = [
                'payable_id' => $payable->id,
                'title_number' => $payable->title_number,
                'status' => $payable->status,
                'old_paid_at' => $current,
                'new_paid_at' => $paymentDate,
                'approved_date' => $approvedDate,
                'gestor_id' => $info['gestor_id'],
                'senior_id' => $payable->senior_id,
            ];

            if ($limit > 0 && count($candidates) >= $limit) {
                break;
            }
        }

        $this->info('Candidatos a correção: '.count($candidates));
        if ($candidates === []) {
            $this->warn('Nada a fazer.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Título', 'Status', 'paid_at atual', 'paymentDate Gestor', 'approved'],
            collect($candidates)->take(30)->map(fn ($c) => [
                $c['payable_id'],
                $c['title_number'],
                $c['status'],
                $c['old_paid_at'] ?? 'null',
                $c['new_paid_at'],
                $c['approved_date'] ?? 'null',
            ])->all()
        );
        if (count($candidates) > 30) {
            $this->line('… +'.(count($candidates) - 30).' omitidos na tabela');
        }

        $backupPath = $this->option('backup')
            ?: storage_path('app/backups/paid_at_fix_'.now()->format('Ymd_His').'.json');

        File::ensureDirectoryExists(dirname($backupPath));
        File::put($backupPath, json_encode([
            'created_at' => now()->toIso8601String(),
            'source' => $source,
            'execute' => $execute,
            'only_approval_fallback' => $onlyApprovalFallback,
            'candidates' => $candidates,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Backup/plano gravado em: {$backupPath}");

        if (! $execute) {
            $this->warn('Dry-run ok. Para aplicar: php artisan gestor:fix-paid-at-from-receipt --execute --force');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Confirma alterar SOMENTE paid_at em '.count($candidates).' título(s)? Status não muda.', false)) {
            $this->warn('Abortado.');

            return self::SUCCESS;
        }

        $updated = 0;
        DB::transaction(function () use ($candidates, &$updated) {
            foreach ($candidates as $c) {
                $payable = Payable::query()->lockForUpdate()->find($c['payable_id']);
                if (! $payable) {
                    continue;
                }

                // Guarda-chuva: nunca mexer em status neste comando
                $statusBefore = $payable->status;
                $old = $payable->paid_at?->toDateString();

                $payable->forceFill([
                    'paid_at' => $c['new_paid_at'],
                ])->save();

                $payable->refresh();
                if ($payable->status !== $statusBefore) {
                    throw new \RuntimeException(
                        "Status mudou inesperadamente no payable #{$payable->id} ({$statusBefore} → {$payable->status}). Rollback."
                    );
                }

                AuditLogger::log(
                    event: 'contas_pagar.paid_at_corrigido_gestor_receipt',
                    module: 'financeiro.contas_pagar',
                    description: "paid_at corrigido do Gestor receipt.paymentDate no título {$payable->title_number}",
                    auditable: $payable,
                    oldValues: ['paid_at' => $old, 'status' => $statusBefore],
                    newValues: [
                        'paid_at' => $c['new_paid_at'],
                        'status' => $payable->status,
                        'gestor_id' => $c['gestor_id'],
                        'approved_date_ignored' => $c['approved_date'],
                    ],
                );

                $updated++;
            }
        });

        $this->info("Atualizados: {$updated}");
        $this->info("Backup permanece em: {$backupPath}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{payment_date: string, approved_date: ?string, gestor_id: ?string}>
     */
    private function collectPaymentDates(array $data): array
    {
        $byPayable = [];

        $walk = function ($node) use (&$walk, &$byPayable) {
            if (! is_array($node)) {
                return;
            }

            $payableId = isset($node['payable_id']) ? (int) $node['payable_id'] : 0;
            $history = $node['history'] ?? null;

            if ($payableId > 0 && is_array($history)) {
                $paymentDate = $this->extractLatestPaymentDate($history);
                if ($paymentDate !== null) {
                    $byPayable[$payableId] = [
                        'payment_date' => $paymentDate,
                        'approved_date' => $this->extractApprovedDate($history),
                        'gestor_id' => isset($node['gestor_id']) ? (string) $node['gestor_id'] : null,
                    ];
                }
            }

            foreach ($node as $v) {
                $walk($v);
            }
        };

        $walk($data);

        return $byPayable;
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function extractLatestPaymentDate(array $history): ?string
    {
        $bestAt = null;
        $bestDate = null;

        foreach ($history as $event) {
            if (($event['type'] ?? '') !== 'receipt-annexed') {
                continue;
            }
            $ms = $event['receipt']['paymentDate'] ?? null;
            if (! is_numeric($ms)) {
                continue;
            }
            $eventAt = isset($event['at']) ? (int) $event['at'] : (int) $ms;
            if ($bestAt !== null && $eventAt < $bestAt) {
                continue;
            }
            $bestAt = $eventAt;
            $bestDate = Carbon::createFromTimestampMs((int) $ms, 'America/Sao_Paulo')->toDateString();
        }

        return $bestDate;
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function extractApprovedDate(array $history): ?string
    {
        $bestAt = null;
        $bestDate = null;

        foreach ($history as $event) {
            if (($event['type'] ?? '') !== 'approved' || ! isset($event['at'])) {
                continue;
            }
            $at = (int) $event['at'];
            if ($bestAt !== null && $at < $bestAt) {
                continue;
            }
            $bestAt = $at;
            $bestDate = Carbon::createFromTimestampMs($at, 'America/Sao_Paulo')->toDateString();
        }

        return $bestDate;
    }
}
