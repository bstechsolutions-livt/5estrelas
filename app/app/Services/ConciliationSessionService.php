<?php

namespace App\Services;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\ConciliationSession;
use App\Models\Payable;
use App\Models\User;
use Carbon\Carbon;

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
            ->with('matchedPayable:id,title_number,supplier_name,amount,paid_at,status')
            ->whereIn('import_id', $importIds)
            ->where('type', 'debit')
            ->orderByDesc('amount')
            ->get();

        $matched = [];
        $ofxOnly = [];
        $ambiguous = [];

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

            if ($tx->match_status === 'unmatched') {
                $ofxOnly[] = $row;
            }
        }

        $matchedPayableIds = $transactions
            ->whereNotNull('matched_payable_id')
            ->whereIn('match_status', ['pending', 'accepted', 'manual'])
            ->pluck('matched_payable_id')
            ->unique()
            ->all();

        $payableOnly = $this->pendingPayablesQuery($referenceDate)
            ->when(! empty($matchedPayableIds), fn ($q) => $q->whereNotIn('id', $matchedPayableIds))
            ->orderByDesc('amount')
            ->get(['id', 'title_number', 'supplier_name', 'amount', 'paid_at', 'status'])
            ->map(fn (Payable $p) => [
                'id' => $p->id,
                'title_number' => $p->title_number,
                'supplier_name' => $p->supplier_name,
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at?->toDateString(),
                'status' => $p->status,
            ])
            ->values()
            ->all();

        $accepted = $transactions->whereIn('match_status', ['accepted', 'manual'])->count();

        return [
            'date' => $date,
            'label' => $this->periodLabel($referenceDate),
            'imports' => $imports,
            'accounts' => $accounts,
            'kpis' => [
                'imports' => count($imports),
                'accounts' => count($accounts),
                'matched' => count($matched),
                'ofx_only' => count($ofxOnly),
                'payable_only' => count($payableOnly),
                'ambiguous' => count($ambiguous),
                'accepted' => $accepted,
            ],
            'matched' => $matched,
            'ofx_only' => $ofxOnly,
            'payable_only' => $payableOnly,
            'ambiguous' => $ambiguous,
        ];
    }

    private function mapTransactionRow(BankTransaction $tx): array
    {
        $payable = $tx->matchedPayable;

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
            'payable' => $payable ? [
                'id' => $payable->id,
                'title_number' => $payable->title_number,
                'supplier_name' => $payable->supplier_name,
                'amount' => (float) $payable->amount,
                'paid_at' => $payable->paid_at?->toDateString(),
                'status' => $payable->status,
            ] : null,
            'ambiguous_candidates' => $tx->raw_data['ambiguous_candidates'] ?? [],
        ];
    }

    public function periodLabel(Carbon $referenceDate): string
    {
        return $referenceDate->locale('pt_BR')->translatedFormat('d/m/Y');
    }
}
