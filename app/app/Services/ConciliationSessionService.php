<?php

namespace App\Services;

use App\Models\BankDayOperation;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\ConciliationSession;
use App\Models\Payable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ConciliationSessionService
{
    public function parseReferenceDate(?string $date, ?int $year = null, ?int $month = null, ?int $day = null): Carbon
    {
        if ($date !== null && $date !== '') {
            return Carbon::parse($date)->startOfDay();
        }

        if ($year !== null && $month !== null) {
            return Carbon::createFromDate($year, $month, $day ?? 1)->startOfDay();
        }

        return now()->startOfDay();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function dayBounds(Carbon $referenceDate): array
    {
        $start = $referenceDate->copy()->startOfDay();

        return [$start, $start->copy()->endOfDay()];
    }

    public function resolve(int $bankAccountId, Carbon $referenceDate, ?User $user = null): ConciliationSession
    {
        $date = $referenceDate->toDateString();

        $existing = ConciliationSession::query()
            ->where('bank_account_id', $bankAccountId)
            ->whereDate('reference_date', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return ConciliationSession::create([
                'bank_account_id' => $bankAccountId,
                'reference_date' => $date,
                'status' => 'open',
                'created_by' => $user?->id,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return ConciliationSession::query()
                ->where('bank_account_id', $bankAccountId)
                ->whereDate('reference_date', $date)
                ->firstOrFail();
        }
    }

    public function summary(ConciliationSession $session): array
    {
        return $this->summaryForDate($session->reference_date->copy()->startOfDay(), $session->bank_account_id);
    }

    public function pendingPayablesQuery(Carbon $referenceDate)
    {
        return Payable::query()
            ->whereIn('status', ['pago', 'aguardando_conciliacao'])
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', $referenceDate->toDateString());
    }

    public function matchedPayableIdsInSession(ConciliationSession $session): array
    {
        $importIds = $session->imports()->pluck('id');

        return BankTransaction::query()
            ->whereIn('import_id', $importIds)
            ->whereNotNull('matched_payable_id')
            ->whereIn('match_status', ['pending', 'accepted', 'manual'])
            ->pluck('matched_payable_id')
            ->unique()
            ->values()
            ->all();
    }

    public function matchedPayableIdsForDate(Carbon $referenceDate): array
    {
        $date = $referenceDate->toDateString();
        $importIds = BankStatementImport::query()
            ->whereIn('conciliation_session_id', function ($q) use ($date) {
                $q->select('id')
                    ->from('conciliation_sessions')
                    ->whereDate('reference_date', $date);
            })
            ->pluck('id');

        return BankTransaction::query()
            ->whereIn('import_id', $importIds)
            ->whereNotNull('matched_payable_id')
            ->whereIn('match_status', ['pending', 'accepted', 'manual'])
            ->pluck('matched_payable_id')
            ->unique()
            ->values()
            ->all();
    }

    public function existingFitIdsInSession(ConciliationSession $session): array
    {
        $importIds = $session->imports()->pluck('id');

        return BankTransaction::query()
            ->whereIn('import_id', $importIds)
            ->whereNotNull('fitid')
            ->pluck('fitid')
            ->all();
    }

    public function summaryForDate(Carbon $referenceDate, ?int $bankAccountId = null): array
    {
        $date = $referenceDate->toDateString();

        $sessionQuery = ConciliationSession::query()->whereDate('reference_date', $date);

        if ($bankAccountId !== null) {
            $sessionQuery->where('bank_account_id', $bankAccountId);
        }

        $importIds = BankStatementImport::query()
            ->whereIn('conciliation_session_id', $sessionQuery->select('id'))
            ->pluck('id');

        return [
            'pending_payables' => $this->pendingPayablesQuery($referenceDate)->count(),
            'imports' => $importIds->count(),
            'bank_transactions' => BankTransaction::whereIn('import_id', $importIds)->count(),
            'suggested_matches' => BankTransaction::whereIn('import_id', $importIds)
                ->where('match_status', 'pending')
                ->where('match_confidence', '!=', 'none')
                ->count(),
            'accepted_matches' => BankTransaction::whereIn('import_id', $importIds)
                ->whereIn('match_status', ['accepted', 'manual'])
                ->count(),
            'unmatched_debits' => BankTransaction::whereIn('import_id', $importIds)
                ->where('type', 'debit')
                ->where('match_status', 'unmatched')
                ->count(),
        ];
    }

    public function recentDaySummaries(int $limit = 14): array
    {
        $dates = ConciliationSession::query()
            ->select('reference_date')
            ->distinct()
            ->orderByDesc('reference_date')
            ->limit($limit)
            ->pluck('reference_date');

        return $dates->map(function ($raw) {
            $date = Carbon::parse($raw)->startOfDay();
            $report = $this->dayReport($date);

            return [
                'date' => $date->toDateString(),
                'label' => $this->periodLabel($date),
                'imports' => $report['kpis']['imports'],
                'accounts' => $report['kpis']['accounts'],
                'suggested' => $report['kpis']['matched'],
                'unmatched' => $report['kpis']['ofx_only'],
                'ambiguous' => $report['kpis']['ambiguous'],
                'pending_payables' => $report['kpis']['payable_only'],
            ];
        })->values()->all();
    }

    public function dayReport(Carbon $referenceDate): array
    {
        $date = $referenceDate->toDateString();

        $sessions = ConciliationSession::query()
            ->with(['bankAccount:id,name,bank_code,account_number,account_digit'])
            ->whereDate('reference_date', $date)
            ->get();

        $importIds = BankStatementImport::query()
            ->whereIn('conciliation_session_id', $sessions->pluck('id'))
            ->pluck('id');

        $imports = BankStatementImport::query()
            ->with(['bankAccount:id,name,bank_code,account_number'])
            ->whereIn('id', $importIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BankStatementImport $i) => [
                'id' => $i->id,
                'file_name' => $i->file_name,
                'bank_name' => $i->bank_name,
                'account_number' => $i->account_number,
                'bank_account_id' => $i->bank_account_id,
                'bank_account_name' => $i->bankAccount?->name,
                'transaction_count' => $i->transaction_count,
                'matched_count' => $i->matched_count,
                'day_conciliated_at' => $i->day_conciliated_at?->toIso8601String(),
                'created_at' => $i->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $accounts = $sessions
            ->filter(fn (ConciliationSession $s) => $s->bankAccount !== null)
            ->map(fn (ConciliationSession $s) => [
                'id' => $s->bankAccount->id,
                'name' => $s->bankAccount->name,
                'bank_code' => $s->bankAccount->bank_code,
                'account_number' => $s->bankAccount->account_number,
            ])
            ->unique('id')
            ->values()
            ->all();

        $transactions = BankTransaction::query()
            ->with([
                'matchedPayable:id,title_number,nickname,description,supplier_name,amount,paid_at,due_date,status,codemp,codfil',
                'import:id,bank_account_id,bank_name,account_number,file_name',
                'import.bankAccount:id,name,bank_code',
            ])
            ->whereIn('import_id', $importIds)
            ->where('type', 'debit')
            ->orderByDesc('amount')
            ->get();

        $matchedPayables = $transactions->pluck('matchedPayable')->filter();
        Payable::attachEmpresaNome($matchedPayables);
        Payable::attachFilialNome($matchedPayables);

        $matched = [];
        $ofxOnly = [];
        $ambiguous = [];
        $bankOps = [];
        $classifier = app(OfxBankOperationClassifier::class);

        foreach ($transactions as $tx) {
            $row = $this->mapTransactionRow($tx);

            if (($tx->raw_data['ambiguous'] ?? false) === true && $tx->match_status === 'unmatched') {
                $ambiguous[] = $row;

                continue;
            }

            if (in_array($tx->match_status, ['pending', 'accepted', 'manual'], true) && $tx->matched_payable_id) {
                $matched[] = $row;

                continue;
            }

            // Já gravado como tarifa/aplicação/resgate no conciliar dia
            if ($tx->match_status === 'non_payable') {
                $category = $tx->raw_data['bank_operation_category']
                    ?? $classifier->classify($tx->description, $tx->memo);
                if ($category) {
                    $row['operation_category'] = $category;
                    $bankOps[] = $row;
                }

                continue;
            }

            // unmatched + rejected (legado) → só OFX ou operação bancária
            if (in_array($tx->match_status, ['unmatched', 'rejected'], true)) {
                $category = $classifier->classify($tx->description, $tx->memo);
                if ($category) {
                    $row['operation_category'] = $category;
                    $bankOps[] = $row;
                } else {
                    $ofxOnly[] = $row;
                }
            }
        }

        $this->enrichAmbiguousCandidates($ambiguous);

        $matchedPayableIds = $transactions
            ->whereNotNull('matched_payable_id')
            ->whereIn('match_status', ['pending', 'accepted', 'manual'])
            ->pluck('matched_payable_id')
            ->unique()
            ->all();

        $payableOnlyModels = $this->pendingPayablesQuery($referenceDate)
            ->when(! empty($matchedPayableIds), fn ($q) => $q->whereNotIn('id', $matchedPayableIds))
            ->orderByDesc('amount')
            ->get(['id', 'title_number', 'supplier_name', 'amount', 'paid_at', 'status', 'codemp', 'codfil']);

        Payable::attachEmpresaNome($payableOnlyModels);
        Payable::attachFilialNome($payableOnlyModels);

        $payableOnly = $payableOnlyModels
            ->map(fn (Payable $p) => [
                'id' => $p->id,
                'title_number' => $p->title_number,
                'supplier_name' => $p->supplier_name,
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at?->toDateString(),
                'status' => $p->status,
                'codemp' => $p->codemp,
                'empresa_nome' => $p->empresa_nome,
                'filial_nome' => $p->filial_nome,
                'filial_label' => $p->filial_label,
            ])
            ->values()
            ->all();

        $accepted = $transactions->whereIn('match_status', ['accepted', 'manual'])->count();
        $pendingMatches = collect($matched)->where('match_status', 'pending')->count();
        $dayAlreadyConciliated = collect($imports)->contains(fn ($i) => ! empty($i['day_conciliated_at'] ?? null))
            || BankDayOperation::query()->whereDate('reference_date', $date)->exists();

        $opsByCategory = [
            'tarifa' => collect($bankOps)->where('operation_category', 'tarifa')->count(),
            'aplicacao' => collect($bankOps)->where('operation_category', 'aplicacao')->count(),
            'resgate' => collect($bankOps)->where('operation_category', 'resgate')->count(),
        ];

        $blockers = [];
        if ($dayAlreadyConciliated) {
            $blockers[] = 'Este dia já foi conciliado.';
        }
        if ($pendingMatches > 0) {
            $blockers[] = "Há {$pendingMatches} sugestão(ões) pendente(s) — aceite ou rejeite todas.";
        }
        if (count($ambiguous) > 0) {
            $blockers[] = 'Há matches ambíguos — vincule ou resolva antes.';
        }
        if (count($ofxOnly) > 0) {
            $blockers[] = 'Há débitos só no OFX (sem título) que não são tarifa/aplicação/resgate — vincule ou trate antes.';
        }

        return [
            'date' => $date,
            'label' => $this->periodLabel($referenceDate),
            'imports' => $imports,
            'accounts' => $accounts,
            'day_conciliated' => $dayAlreadyConciliated,
            'can_conciliate_day' => empty($blockers) && count($imports) > 0,
            'conciliate_blockers' => $blockers,
            'summary' => [
                'accepted' => $accepted,
                'bank_ops' => count($bankOps),
                'tarifas' => $opsByCategory['tarifa'],
                'aplicacoes' => $opsByCategory['aplicacao'],
                'resgates' => $opsByCategory['resgate'],
                'ofx_files' => count($imports),
                'payable_only' => count($payableOnly),
            ],
            'kpis' => [
                'imports' => count($imports),
                'accounts' => count($accounts),
                'matched' => count($matched),
                'ofx_only' => count($ofxOnly),
                'bank_ops' => count($bankOps),
                'payable_only' => count($payableOnly),
                'ambiguous' => count($ambiguous),
                'accepted' => $accepted,
            ],
            'matched' => $matched,
            'ofx_only' => $ofxOnly,
            'bank_ops' => $bankOps,
            'payable_only' => $payableOnly,
            'ambiguous' => $ambiguous,
        ];
    }

    private function mapTransactionRow(BankTransaction $tx): array
    {
        $payable = $tx->matchedPayable;
        $import = $tx->import;

        return [
            'id' => $tx->id,
            'import_id' => $tx->import_id,
            'date' => $tx->date?->toDateString(),
            'amount' => (float) $tx->amount,
            'description' => $tx->description,
            'memo' => $tx->memo,
            'match_status' => $tx->match_status,
            'match_confidence' => $tx->match_confidence,
            'matched_payable_id' => $tx->matched_payable_id,
            'bank_account_name' => $import?->bankAccount?->name ?? $import?->bank_name,
            'ofx_file_name' => $import?->file_name,
            'payable' => $payable ? [
                'id' => $payable->id,
                'title_number' => $payable->title_number,
                'nickname' => $payable->nickname,
                'description' => $payable->description,
                'supplier_name' => $payable->supplier_name,
                'amount' => (float) $payable->amount,
                'paid_at' => $payable->paid_at?->toDateString(),
                'due_date' => $payable->due_date?->toDateString(),
                'status' => $payable->status,
                'codemp' => $payable->codemp,
                'empresa_nome' => $payable->empresa_nome,
                'filial_nome' => $payable->filial_nome,
                'filial_label' => $payable->filial_label,
            ] : null,
            'ambiguous_candidates' => $tx->raw_data['ambiguous_candidates'] ?? [],
        ];
    }

    /** Enriquece candidatos ambíguos com empresa/filial. */
    private function enrichAmbiguousCandidates(array &$ambiguousRows): void
    {
        $ids = collect($ambiguousRows)
            ->flatMap(fn ($row) => collect($row['ambiguous_candidates'] ?? [])->pluck('payable_id'))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $payables = Payable::query()
            ->whereIn('id', $ids)
            ->get(['id', 'title_number', 'supplier_name', 'amount', 'paid_at', 'codemp', 'codfil']);

        Payable::attachEmpresaNome($payables);
        Payable::attachFilialNome($payables);

        $byId = $payables->keyBy('id');

        foreach ($ambiguousRows as &$row) {
            $row['ambiguous_candidates'] = collect($row['ambiguous_candidates'] ?? [])
                ->map(function ($c) use ($byId) {
                    $p = $byId->get($c['payable_id'] ?? null);
                    if (! $p) {
                        return $c;
                    }

                    return array_merge($c, [
                        'title_number' => $p->title_number,
                        'supplier_name' => $p->supplier_name,
                        'empresa_nome' => $p->empresa_nome,
                        'filial_nome' => $p->filial_nome,
                        'filial_label' => $p->filial_label,
                        'codemp' => $p->codemp,
                    ]);
                })
                ->values()
                ->all();
        }
        unset($row);
    }

    /**
     * Apaga todos os OFX/sessões do dia para recomeçar do zero.
     * Não altera títulos (payables). Bloqueia se houver vínculo com título já conciliado.
     *
     * @return array{deleted_imports: int, deleted_sessions: int}
     */
    public function resetDay(Carbon $referenceDate, ?User $user = null): array
    {
        $date = $referenceDate->toDateString();

        $sessions = ConciliationSession::query()
            ->whereDate('reference_date', $date)
            ->get();

        if ($sessions->isEmpty()) {
            return ['deleted_imports' => 0, 'deleted_sessions' => 0];
        }

        if (BankDayOperation::query()->whereDate('reference_date', $date)->exists()) {
            throw new \RuntimeException(
                'Não é possível resetar este dia: já foi conciliado (tarifas/aplicações/resgates e OFX preservados).'
            );
        }

        $imports = BankStatementImport::query()
            ->whereIn('conciliation_session_id', $sessions->pluck('id'))
            ->get();

        if ($imports->contains(fn (BankStatementImport $i) => $i->day_conciliated_at !== null)) {
            throw new \RuntimeException(
                'Não é possível resetar este dia: há extratos OFX já arquivados na conciliação do dia.'
            );
        }

        $blocked = BankTransaction::query()
            ->whereIn('import_id', $imports->pluck('id'))
            ->whereIn('match_status', ['accepted', 'manual'])
            ->whereHas('matchedPayable', fn ($q) => $q->where('status', 'conciliado'))
            ->exists();

        if ($blocked) {
            throw new \RuntimeException(
                'Não é possível resetar este dia: há matches vinculados a títulos já conciliados.'
            );
        }

        $deletedImports = 0;
        foreach ($imports as $import) {
            AuditLogger::log(
                event: 'contas_pagar.ofx_excluido',
                module: 'financeiro.contas_pagar',
                description: "Reset do dia {$date}: OFX excluído {$import->file_name} ({$import->bank_name})",
                auditable: $import,
                oldValues: [
                    'bank_name' => $import->bank_name,
                    'account_number' => $import->account_number,
                    'transaction_count' => $import->transaction_count,
                    'reference_date' => $date,
                    'reset_by' => $user?->id,
                ],
            );

            if ($import->file_path) {
                Storage::disk('local')->delete($import->file_path);
            }

            $import->delete();
            $deletedImports++;
        }

        $deletedSessions = 0;
        foreach ($sessions as $session) {
            $session->delete();
            $deletedSessions++;
        }

        AuditLogger::log(
            event: 'contas_pagar.conciliacao_dia_reset',
            module: 'financeiro.contas_pagar',
            description: "Conciliação do dia {$date} resetada: {$deletedImports} extrato(s), {$deletedSessions} sessão(ões)",
            newValues: [
                'date' => $date,
                'deleted_imports' => $deletedImports,
                'deleted_sessions' => $deletedSessions,
                'user_id' => $user?->id,
            ],
        );

        return [
            'deleted_imports' => $deletedImports,
            'deleted_sessions' => $deletedSessions,
        ];
    }

    public function periodLabel(Carbon $referenceDate): string
    {
        return $referenceDate->locale('pt_BR')->translatedFormat('d/m/Y');
    }
}
