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

        if (!$alcada->isAssigned($user, 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }

        $import = BankStatementImport::findOrFail($importId);
        $transactions = $import->transactions()
            ->whereIn('match_status', ['accepted', 'manual'])
            ->whereNotNull('matched_payable_id')
            ->get();

        if ($transactions->isEmpty()) {
            return ['conciliated' => 0, 'skipped' => 0, 'errors' => ['Nenhuma transação aceita para conciliar.']];
        }

        $conciliated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($transactions as $tx) {
            $result = DB::transaction(function () use ($tx, $user, $import) {
                $payable = Payable::whereKey($tx->matched_payable_id)->lockForUpdate()->first();

                if (!$payable || ! in_array($payable->status, ['pago', 'aguardando_conciliacao'], true)) {
                    $tx->update(['match_status' => 'rejected', 'raw_data' => array_merge($tx->raw_data ?? [], ['skip_reason' => 'Título não está mais com status pago'])]);

                    return 'skipped';
                }

                $payable->update([
                    'status' => 'conciliado',
                    'conciliated_at' => now()->toDateString(),
                    'conciliated_by' => $user->id,
                    'conciliation_notes' => "Conciliação via importação OFX #{$import->id} ({$import->bank_name} - {$import->account_number})",
                ]);

                PayableComment::create([
                    'payable_id' => $payable->id,
                    'user_id' => $user->id,
                    'body' => "Conciliado via importação OFX #{$import->id} ({$import->bank_name})",
                    'type' => 'conciliation',
                ]);

                return 'conciliated';
            });

            if ($result === 'conciliated') {
                $conciliated++;
            } else {
                $skipped++;
            }
        }

        // Audit log (consolidated)
        AuditLogger::log(
            event: 'contas_pagar.conciliacao_lote',
            module: 'financeiro.contas_pagar',
            description: "Conciliação em lote: {$conciliated} títulos conciliados via OFX #{$import->id} ({$import->bank_name})",
            auditable: $import,
            newValues: ['conciliated' => $conciliated, 'skipped' => $skipped, 'import_id' => $import->id],
        );

        // Update import matched_count
        $import->update(['matched_count' => $import->transactions()->where('match_status', 'accepted')->count()]);

        return ['conciliated' => $conciliated, 'skipped' => $skipped, 'errors' => $errors];
    }
}
