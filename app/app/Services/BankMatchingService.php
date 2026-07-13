<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\BankStatementImport;
use App\Models\Payable;
use Carbon\Carbon;

class BankMatchingService
{
    private const AMOUNT_TOLERANCE = 0.01;
    private const HIGH_CONFIDENCE_DAYS = 2;
    private const MEDIUM_CONFIDENCE_DAYS = 5;

    /**
     * Run matching for all transactions of an import.
     * @return array{matched: int, unmatched: int}
     */
    public function run(int $importId): array
    {
        $import = BankStatementImport::findOrFail($importId);
        $transactions = $import->transactions()->get();

        $matched = 0;
        $unmatched = 0;

        // Track payable IDs already matched in this import to detect duplicates
        $matchedPayableIds = [];

        foreach ($transactions as $tx) {
            // Only match DEBIT transactions (money going out = payments)
            if (strtoupper($tx->type) !== 'DEBIT') {
                $tx->update(['match_status' => 'unmatched', 'match_confidence' => 'none']);
                $unmatched++;
                continue;
            }

            $candidates = $this->findCandidates($tx, $matchedPayableIds);

            if (empty($candidates)) {
                $tx->update(['match_status' => 'unmatched', 'match_confidence' => 'none']);
                $unmatched++;
                continue;
            }

            // Best candidate (first in array, sorted by confidence)
            $best = $candidates[0];
            $payableId = $best['payable_id'];

            // Detect duplicate: same payable matched to multiple transactions
            $rawData = $tx->raw_data ?? [];
            if (in_array($payableId, $matchedPayableIds)) {
                $rawData['possible_duplicate'] = true;
            }
            $matchedPayableIds[] = $payableId;

            $tx->update([
                'matched_payable_id' => $payableId,
                'match_status' => 'pending',
                'match_confidence' => $best['confidence'],
                'raw_data' => $rawData,
            ]);
            $matched++;
        }

        // Update import counters
        $import->update([
            'matched_count' => $matched,
            'status' => 'done',
        ]);

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    /**
     * Find candidate Payables for a bank transaction.
     * Returns array of candidates sorted by confidence (high > medium > low).
     */
    public function findCandidates(BankTransaction $tx, array $alreadyMatchedPayableIds = []): array
    {
        $absAmount = abs((float) $tx->amount);

        // Find payables with matching amount (+-0.01) and status = pago
        // Use CAST for SQLite compatibility (decimal columns stored as text affinity)
        $driver = Payable::query()->getConnection()->getDriverName();
        $amountExpr = $driver === 'sqlite'
            ? 'ABS(CAST(amount AS REAL) - CAST(? AS REAL)) <= CAST(? AS REAL)'
            : 'ABS(amount - ?) <= ?';

        $payables = Payable::whereIn('status', ['pago', 'aguardando_conciliacao'])
            ->whereRaw($amountExpr, [$absAmount, self::AMOUNT_TOLERANCE])
            ->get(['id', 'amount', 'paid_at', 'title_number', 'supplier_name']);

        $candidates = [];
        foreach ($payables as $payable) {
            $confidence = $this->calculateConfidence($tx->date, $payable->paid_at);
            $candidates[] = [
                'payable_id' => $payable->id,
                'confidence' => $confidence,
                'title_number' => $payable->title_number,
                'supplier_name' => $payable->supplier_name,
                'amount' => $payable->amount,
                'paid_at' => $payable->paid_at?->format('Y-m-d'),
            ];
        }

        // Sort by confidence: high > medium > low
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($candidates, fn ($a, $b) => ($order[$a['confidence']] ?? 3) <=> ($order[$b['confidence']] ?? 3));

        return $candidates;
    }

    /**
     * Calculate confidence based on days difference.
     */
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
