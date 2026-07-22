<?php

namespace App\Services;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BatchConciliationService
{
    public function execute(int $importId, User $user): array
    {
        $alcada = app(PayableAlcadaService::class);

        if (! $alcada->isAssigned($user, 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }

        $import = BankStatementImport::findOrFail($importId);
        $transactions = $import->transactions()
            ->whereIn('match_status', ['accepted', 'manual'])
            ->whereNotNull('matched_payable_id')
            ->get();

        return $this->conciliateTransactions($transactions, $user, $import);
    }

    public function executeForDate(string $date, User $user): array
    {
        $alcada = app(PayableAlcadaService::class);

        if (! $alcada->isAssigned($user, 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }

        $importIds = BankStatementImport::query()
            ->whereIn('conciliation_session_id', function ($q) use ($date) {
                $q->select('id')
                    ->from('conciliation_sessions')
                    ->whereDate('reference_date', $date);
            })
            ->pluck('id');

        $transactions = BankTransaction::query()
            ->with('import')
            ->whereIn('import_id', $importIds)
            ->whereIn('match_status', ['accepted', 'manual'])
            ->whereNotNull('matched_payable_id')
            ->get();

        if ($transactions->isEmpty()) {
            return ['conciliated' => 0, 'skipped' => 0, 'errors' => ['Nenhuma transação aceita para conciliar neste dia.']];
        }

        $result = $this->conciliateTransactions($transactions, $user, null, $date);

        AuditLogger::log(
            event: 'contas_pagar.conciliacao_lote_dia',
            module: 'financeiro.contas_pagar',
            description: "Conciliação em lote do dia {$date}: {$result['conciliated']} títulos",
            newValues: ['date' => $date, 'conciliated' => $result['conciliated'], 'skipped' => $result['skipped']],
        );

        return $result;
    }

    private function conciliateTransactions($transactions, User $user, ?BankStatementImport $import = null, ?string $date = null): array
    {
        if ($transactions->isEmpty()) {
            return ['conciliated' => 0, 'skipped' => 0, 'errors' => ['Nenhuma transação aceita para conciliar.']];
        }

        $conciliated = 0;
        $skipped = 0;
        $errors = [];
        $touchedImports = [];

        foreach ($transactions as $tx) {
            $txImport = $import ?? $tx->import;
            if ($txImport === null) {
                $skipped++;

                continue;
            }

            $result = DB::transaction(function () use ($tx, $user, $txImport) {
                $payable = Payable::whereKey($tx->matched_payable_id)->lockForUpdate()->first();

                if (! $payable || ! in_array($payable->status, ['pago', 'aguardando_conciliacao'], true)) {
                    $tx->update([
                        'match_status' => 'rejected',
                        'raw_data' => array_merge($tx->raw_data ?? [], [
                            'skip_reason' => 'Título não está mais com status pago',
                        ]),
                    ]);

                    return 'skipped';
                }

                $payable->update([
                    'status' => 'conciliado',
                    'conciliated_at' => now()->toDateString(),
                    'conciliated_by' => $user->id,
                    'conciliation_notes' => "Conciliação via importação OFX #{$txImport->id} ({$txImport->bank_name} - {$txImport->account_number})",
                ]);

                PayableComment::create([
                    'payable_id' => $payable->id,
                    'user_id' => $user->id,
                    'body' => "Conciliado via importação OFX #{$txImport->id} ({$txImport->bank_name})",
                    'type' => 'conciliation',
                ]);

                return 'conciliated';
            });

            $touchedImports[$txImport->id] = $txImport;

            if ($result === 'conciliated') {
                $conciliated++;
            } else {
                $skipped++;
            }
        }

        if ($import !== null && $date === null) {
            AuditLogger::log(
                event: 'contas_pagar.conciliacao_lote',
                module: 'financeiro.contas_pagar',
                description: "Conciliação em lote: {$conciliated} títulos conciliados via OFX #{$import->id} ({$import->bank_name})",
                auditable: $import,
                newValues: ['conciliated' => $conciliated, 'skipped' => $skipped, 'import_id' => $import->id],
            );
        }

        foreach ($touchedImports as $touched) {
            $touched->update([
                'matched_count' => $touched->transactions()->whereIn('match_status', ['accepted', 'manual'])->count(),
            ]);
        }

        return ['conciliated' => $conciliated, 'skipped' => $skipped, 'errors' => $errors];
    }
}
