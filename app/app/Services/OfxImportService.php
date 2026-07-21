<?php

namespace App\Services;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\ConciliationSession;
use App\Models\User;
use App\Services\Ofx\OfxParseException;
use App\Services\Ofx\OfxParseResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OfxImportService
{
    public function __construct(
        private OfxParserService $parser,
        private BankMatchingService $matcher,
        private BankAccountMatcher $accountMatcher,
    ) {}

    /**
     * @param  array<string>  $skipFitIds  FITIDs já importados na sessão (dedupe)
     * @return array{import: BankStatementImport, skipped_duplicates: int, parse: OfxParseResult}
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
        ?\Carbon\Carbon $paidFrom = null,
        ?\Carbon\Carbon $paidTo = null,
    ): array {
        $content = file_get_contents($file->getRealPath());
        $result = $this->parser->parse($content);

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

        foreach ($result->transactions as $index => $tx) {
            $fitid = $tx->fitid ?: "import_{$import->id}_seq_{$index}";

            if (in_array($fitid, $skipFitIds, true)) {
                $skippedDuplicates++;
                continue;
            }

            BankTransaction::create([
                'import_id' => $import->id,
                'fitid' => $fitid,
                'date' => $tx->date,
                'amount' => $tx->amount,
                'type' => strtolower($tx->type),
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

        $matchResult = $this->matcher->run(
            $import->id,
            $paidFrom,
            $paidTo,
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
        ];
    }
}
