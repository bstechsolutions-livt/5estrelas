<?php

namespace App\Services;

use App\Models\BankDayOperation;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BatchConciliationService
{
    public function __construct(
        private OfxBankOperationClassifier $classifier,
        private ConciliationSessionService $sessions,
    ) {}

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

        $report = $this->sessions->dayReport(
            $this->sessions->parseReferenceDate($date)
        );

        if (! empty($report['conciliate_blockers'])) {
            return [
                'conciliated' => 0,
                'skipped' => 0,
                'operations_saved' => 0,
                'imports_retained' => 0,
                'errors' => $report['conciliate_blockers'],
            ];
        }

        $importIds = collect($report['imports'])->pluck('id')->all();

        $transactions = BankTransaction::query()
            ->with('import')
            ->whereIn('import_id', $importIds)
            ->whereIn('match_status', ['accepted', 'manual'])
            ->whereNotNull('matched_payable_id')
            ->get();

        $opsTransactions = BankTransaction::query()
            ->with('import.bankAccount')
            ->whereIn('import_id', $importIds)
            ->where('type', 'debit')
            ->whereIn('match_status', ['unmatched', 'rejected'])
            ->get()
            ->filter(fn (BankTransaction $tx) => $this->classifier->classify($tx->description, $tx->memo) !== null)
            ->values();

        if ($transactions->isEmpty() && $opsTransactions->isEmpty()) {
            return [
                'conciliated' => 0,
                'skipped' => 0,
                'operations_saved' => 0,
                'imports_retained' => 0,
                'errors' => ['Nada para conciliar neste dia (sem títulos aceitos nem tarifas/aplicações/resgates).'],
            ];
        }

        $result = $this->conciliateTransactions($transactions, $user, null, $date);
        $opsSaved = $this->persistBankOperations($opsTransactions, $date, $user);
        $retained = $this->retainOfxImports($importIds, $date);

        AuditLogger::log(
            event: 'contas_pagar.conciliacao_lote_dia',
            module: 'financeiro.contas_pagar',
            description: "Conciliação completa do dia {$date}: {$result['conciliated']} títulos, {$opsSaved} operações bancárias, {$retained} OFX preservado(s)",
            newValues: [
                'date' => $date,
                'conciliated' => $result['conciliated'],
                'skipped' => $result['skipped'],
                'operations_saved' => $opsSaved,
                'imports_retained' => $retained,
            ],
        );

        $result['operations_saved'] = $opsSaved;
        $result['imports_retained'] = $retained;

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BankTransaction>  $transactions
     */
    private function persistBankOperations($transactions, string $date, User $user): int
    {
        $saved = 0;

        foreach ($transactions as $tx) {
            $category = $this->classifier->classify($tx->description, $tx->memo);
            if ($category === null) {
                continue;
            }

            $import = $tx->import;
            if ($import === null) {
                continue;
            }

            DB::transaction(function () use ($tx, $import, $category, $date, $user, &$saved) {
                $retainedPath = $import->retained_path ?: $import->file_path;

                BankDayOperation::query()->updateOrCreate(
                    ['bank_transaction_id' => $tx->id],
                    [
                        'reference_date' => $date,
                        'category' => $category,
                        'description' => $tx->description ?: $tx->memo,
                        'amount' => abs((float) $tx->amount),
                        'transaction_date' => $tx->date?->toDateString() ?? $date,
                        'import_id' => $import->id,
                        'bank_account_id' => $import->bank_account_id,
                        'fitid' => $tx->fitid,
                        'ofx_file_name' => $import->file_name,
                        'ofx_file_path' => $retainedPath,
                        'conciliated_by' => $user->id,
                        'conciliated_at' => now(),
                    ]
                );

                $tx->update([
                    'match_status' => 'non_payable',
                    'match_confidence' => 'none',
                    'matched_payable_id' => null,
                    'raw_data' => array_merge($tx->raw_data ?? [], [
                        'bank_operation_category' => $category,
                        'ambiguous' => false,
                        'ambiguous_candidates' => [],
                    ]),
                ]);

                $saved++;
            });
        }

        return $saved;
    }

    /**
     * Copia OFX para pasta permanente e marca o import como conciliado no dia.
     *
     * @param  array<int>  $importIds
     */
    private function retainOfxImports(array $importIds, string $date): int
    {
        $retained = 0;

        foreach (BankStatementImport::query()->whereIn('id', $importIds)->get() as $import) {
            $target = "ofx/retained/{$date}/{$import->id}_{$import->file_name}";

            if ($import->file_path && Storage::disk('local')->exists($import->file_path)) {
                Storage::disk('local')->makeDirectory("ofx/retained/{$date}");
                if ($import->file_path !== $target) {
                    Storage::disk('local')->copy($import->file_path, $target);
                }
            } elseif ($import->retained_path) {
                $target = $import->retained_path;
            }

            $import->update([
                'retained_path' => $target,
                'day_conciliated_at' => now(),
                'status' => 'day_conciliated',
            ]);

            BankDayOperation::query()
                ->where('import_id', $import->id)
                ->whereDate('reference_date', $date)
                ->update(['ofx_file_path' => $target]);

            $retained++;
        }

        return $retained;
    }

    private function conciliateTransactions($transactions, User $user, ?BankStatementImport $import = null, ?string $date = null): array
    {
        if ($transactions->isEmpty()) {
            return ['conciliated' => 0, 'skipped' => 0, 'errors' => []];
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
