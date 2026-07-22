<?php

namespace App\Services;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\ConciliationSession;
use App\Models\User;
use App\Services\Ofx\OfxParseException;
use App\Services\Ofx\OfxParseResult;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class OfxImportService
{
    public const PERIOD_NOT_ALLOWED = 'OFX de período não permitido — use extrato de um único dia.';

    public function __construct(
        private OfxParserService $parser,
        private BankMatchingService $matcher,
        private BankAccountMatcher $accountMatcher,
    ) {}

    /**
     * Valida que o OFX cobre exatamente um dia e devolve essa data.
     *
     * @throws OfxParseException
     */
    public function assertSingleDay(OfxParseResult $result): Carbon
    {
        $periodStart = $result->meta->periodStart?->copy()->startOfDay();
        $periodEnd = $result->meta->periodEnd?->copy()->startOfDay();

        if ($periodStart && $periodEnd && ! $periodStart->equalTo($periodEnd)) {
            throw new OfxParseException(self::PERIOD_NOT_ALLOWED);
        }

        $txDays = [];
        foreach ($result->transactions as $tx) {
            if ($tx->date === null) {
                continue;
            }
            $txDays[$tx->date->toDateString()] = true;
        }

        if (count($txDays) > 1) {
            throw new OfxParseException(self::PERIOD_NOT_ALLOWED);
        }

        if ($periodStart && $periodEnd && $periodStart->equalTo($periodEnd)) {
            return $periodStart;
        }

        if (count($txDays) === 1) {
            return Carbon::parse(array_key_first($txDays))->startOfDay();
        }

        if ($periodStart) {
            return $periodStart;
        }

        throw new OfxParseException(self::PERIOD_NOT_ALLOWED);
    }

    /**
     * @param  array<string>  $skipFitIds
     * @return array{
     *   import: BankStatementImport,
     *   skipped_duplicates: int,
     *   parse: OfxParseResult,
     *   match: array,
     *   statement_date: string,
     *   debit_count: int,
     *   credit_count: int
     * }
     *
     * @throws OfxParseException
     */
    public function importFile(
        UploadedFile $file,
        User $user,
        ?int $bankAccountId = null,
        ?ConciliationSession $session = null,
        array $skipFitIds = [],
        array $alreadyMatchedPayableIds = [],
        ?Carbon $paidFrom = null,
        ?Carbon $paidTo = null,
    ): array {
        $content = file_get_contents($file->getRealPath());
        $result = $this->parser->parse($content);
        $statementDate = $this->assertSingleDay($result);

        $resolvedBankAccountId = $bankAccountId
            ?? $session?->bank_account_id
            ?? $this->accountMatcher->suggest($result->meta->bankId, $result->meta->accountId)?->id;

        $import = BankStatementImport::create([
            'user_id' => $user->id,
            'bank_account_id' => $resolvedBankAccountId,
            'conciliation_session_id' => $session?->id,
            'bank_name' => $result->meta->orgName,
            'bank_id' => $result->meta->bankId,
            'account_number' => $result->meta->accountId ?? 'N/A',
            'branch_number' => $result->meta->branchId,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => '',
            'period_start' => $result->meta->periodStart,
            'period_end' => $result->meta->periodEnd,
            'balance' => $result->meta->balance,
            'status' => 'processing',
            'transaction_count' => 0,
            'matched_count' => 0,
        ]);

        $path = $file->storeAs("ofx/{$import->id}", $file->getClientOriginalName(), 'local');
        $import->update(['file_path' => $path]);

        $skippedDuplicates = 0;
        $seq = 0;
        $debitCount = 0;
        $creditCount = 0;

        foreach ($result->transactions as $index => $tx) {
            $fitid = $tx->fitid ?: "import_{$import->id}_seq_{$index}";

            if (in_array($fitid, $skipFitIds, true)) {
                $skippedDuplicates++;
                continue;
            }

            $type = strtolower($tx->type);
            if (! in_array($type, ['debit', 'credit'], true)) {
                $type = ((float) $tx->amount) < 0 ? 'debit' : 'credit';
            }
            if ($type === 'debit') {
                $debitCount++;
            } else {
                $creditCount++;
            }

            BankTransaction::create([
                'import_id' => $import->id,
                'fitid' => $fitid,
                'date' => $tx->date,
                'amount' => $tx->amount,
                'type' => $type,
                'description' => $tx->name,
                'memo' => $tx->memo,
                'check_number' => $tx->checkNum,
                'match_status' => 'pending',
                'match_confidence' => 'none',
                'raw_data' => $tx->rawData,
            ]);
            $seq++;
            $skipFitIds[] = $fitid;
        }

        $import->update(['transaction_count' => $seq]);

        $matchFrom = $paidFrom?->copy()->startOfDay() ?? $statementDate->copy()->startOfDay();
        $matchTo = $paidTo?->copy()->endOfDay() ?? $statementDate->copy()->endOfDay();

        $matchResult = $this->matcher->run(
            $import->id,
            $matchFrom,
            $matchTo,
            $alreadyMatchedPayableIds,
        );

        $matchedCount = $import->transactions()
            ->where('match_status', 'pending')
            ->where('match_confidence', '!=', 'none')
            ->count();

        $import->update([
            'status' => 'done',
            'matched_count' => $matchedCount,
        ]);

        return [
            'import' => $import->fresh(),
            'skipped_duplicates' => $skippedDuplicates,
            'parse' => $result,
            'match' => $matchResult,
            'statement_date' => $statementDate->toDateString(),
            'debit_count' => $debitCount,
            'credit_count' => $creditCount,
        ];
    }
}
