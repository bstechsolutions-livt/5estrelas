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

        return ConciliationSession::firstOrCreate(
            [
                'bank_account_id' => $bankAccountId,
                'reference_date' => $date,
            ],
            [
                'status' => 'open',
                'created_by' => $user?->id,
            ],
        );
    }

    /**
     * @return array{
     *   pending_payables: int,
     *   imports: int,
     *   bank_transactions: int,
     *   suggested_matches: int,
     *   accepted_matches: int,
     *   unmatched_debits: int
     * }
     */
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

    public function periodLabel(Carbon $referenceDate): string
    {
        return $referenceDate->locale('pt_BR')->translatedFormat('d/m/Y');
    }
}
