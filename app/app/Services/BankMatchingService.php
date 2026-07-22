<?php

namespace App\Services;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Payable;
use Carbon\Carbon;

class BankMatchingService
{
    private const AMOUNT_TOLERANCE = 0.01;

    private const HIGH_CONFIDENCE_DAYS = 2;

    private const MEDIUM_CONFIDENCE_DAYS = 5;

    /**
     * Matching OFX-first: mesmo dia (paid_at) + valor ±0,01.
     * 1 candidato → pending/high; N → unmatched + ambiguous_candidates.
     *
     * @return array{matched: int, unmatched: int, ambiguous: int}
     */
    public function run(
        int $importId,
        ?Carbon $paidFrom = null,
        ?Carbon $paidTo = null,
        array $alreadyMatchedPayableIds = [],
    ): array {
        $import = BankStatementImport::findOrFail($importId);
        $transactions = $import->transactions()->get();

        $matched = 0;
        $unmatched = 0;
        $ambiguous = 0;
        $matchedPayableIds = $alreadyMatchedPayableIds;

        foreach ($transactions as $tx) {
            if (strtoupper($tx->type) !== 'DEBIT') {
                $tx->update(['match_status' => 'unmatched', 'match_confidence' => 'none']);
                $unmatched++;

                continue;
            }

            $day = $tx->date?->toDateString()
                ?? $paidFrom?->toDateString()
                ?? $paidTo?->toDateString();

            $candidates = $this->findCandidatesForDay($tx, $day, $matchedPayableIds);

            if (empty($candidates)) {
                $tx->update([
                    'match_status' => 'unmatched',
                    'match_confidence' => 'none',
                    'matched_payable_id' => null,
                    'raw_data' => array_merge($tx->raw_data ?? [], [
                        'ambiguous' => false,
                        'ambiguous_candidates' => [],
                    ]),
                ]);
                $unmatched++;

                continue;
            }

            if (count($candidates) > 1) {
                $tx->update([
                    'match_status' => 'unmatched',
                    'match_confidence' => 'low',
                    'matched_payable_id' => null,
                    'raw_data' => array_merge($tx->raw_data ?? [], [
                        'ambiguous' => true,
                        'ambiguous_candidates' => $candidates,
                    ]),
                ]);
                $ambiguous++;
                $unmatched++;

                continue;
            }

            $best = $candidates[0];
            $payableId = $best['payable_id'];
            $matchedPayableIds[] = $payableId;

            $tx->update([
                'matched_payable_id' => $payableId,
                'match_status' => 'pending',
                'match_confidence' => 'high',
                'raw_data' => array_merge($tx->raw_data ?? [], [
                    'ambiguous' => false,
                    'ambiguous_candidates' => [],
                ]),
            ]);
            $matched++;
        }

        $import->update([
            'matched_count' => $matched,
            'status' => 'done',
        ]);

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'ambiguous' => $ambiguous,
        ];
    }

    /**
     * @return list<array{payable_id: int, title_number: ?string, supplier_name: ?string, amount: float|string, paid_at: ?string}>
     */
    public function findCandidatesForDay(
        BankTransaction $tx,
        ?string $day,
        array $alreadyMatchedPayableIds = [],
    ): array {
        if ($day === null || $day === '') {
            return [];
        }

        $absAmount = abs((float) $tx->amount);
        $driver = Payable::query()->getConnection()->getDriverName();
        $amountExpr = $driver === 'sqlite'
            ? 'ABS(CAST(amount AS REAL) - CAST(? AS REAL)) <= CAST(? AS REAL)'
            : 'ABS(amount - ?) <= ?';

        $payables = Payable::whereIn('status', ['pago', 'aguardando_conciliacao'])
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', $day)
            ->whereRaw($amountExpr, [$absAmount, self::AMOUNT_TOLERANCE])
            ->when(
                ! empty($alreadyMatchedPayableIds),
                fn ($q) => $q->whereNotIn('id', $alreadyMatchedPayableIds)
            )
            ->get(['id', 'amount', 'paid_at', 'title_number', 'supplier_name']);

        return $payables->map(fn (Payable $payable) => [
            'payable_id' => $payable->id,
            'title_number' => $payable->title_number,
            'supplier_name' => $payable->supplier_name,
            'amount' => $payable->amount,
            'paid_at' => $payable->paid_at?->format('Y-m-d'),
        ])->values()->all();
    }

    public function findCandidates(
        BankTransaction $tx,
        array $alreadyMatchedPayableIds = [],
        ?Carbon $paidFrom = null,
        ?Carbon $paidTo = null,
    ): array {
        $day = $tx->date?->toDateString()
            ?? $paidFrom?->toDateString()
            ?? $paidTo?->toDateString();

        return $this->findCandidatesForDay($tx, $day, $alreadyMatchedPayableIds);
    }

    public function calculateConfidence(Carbon $txDate, ?Carbon $paidAt): string
    {
        if ($paidAt === null) {
            return 'low';
        }

        $diffDays = abs($txDate->diffInDays($paidAt));

        if ($diffDays <= self::HIGH_CONFIDENCE_DAYS) {
            return 'high';
        }
        if ($diffDays <= self::MEDIUM_CONFIDENCE_DAYS) {
            return 'medium';
        }

        return 'low';
    }
}
