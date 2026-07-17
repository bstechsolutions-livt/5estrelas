<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Payable;
use App\Services\AuditLogger;
use App\Services\BankAccountMatcher;
use App\Services\BankMatchingService;
use App\Services\BatchConciliationService;
use App\Services\OfxParserService;
use App\Services\PayableAlcadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BankConciliationController extends Controller
{
    /**
     * Listagem de importações OFX (paginada).
     */
    public function index(Request $request): Response
    {
        $imports = BankStatementImport::query()
            ->with(['user:id,name', 'bankAccount:id,name,bank_code,account_number'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $alcada = app(PayableAlcadaService::class);
        $isConciliador = $alcada->isAssigned($request->user(), 'conciliador');

        return Inertia::render('BankConciliation/Index', [
            'imports' => $imports,
            'isConciliador' => $isConciliador,
            'bankAccounts' => BankAccount::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'bank_code', 'bank_name', 'account_number', 'account_digit'])
                ->map(function (BankAccount $a) {
                    $suffix = collect([
                        $a->bank_code,
                        $a->account_number
                            ? $a->account_number.($a->account_digit ? '-'.$a->account_digit : '')
                            : null,
                    ])->filter()->implode(' / ');

                    return [
                        'id' => $a->id,
                        'name' => $a->name,
                        'bank_code' => $a->bank_code,
                        'label' => $suffix !== '' ? "{$a->name} — {$suffix}" : $a->name,
                    ];
                }),
        ]);
    }

    /**
     * Detalhe de uma importação com transações paginadas + contadores.
     */
    public function show(Request $request, int $importId): Response
    {
        $import = BankStatementImport::with('user:id,name')->findOrFail($importId);

        $query = $import->transactions()
            ->with('matchedPayable:id,title_number,supplier_name,amount,status')
            ->orderByDesc('date');

        if ($request->filled('match_status')) {
            $query->where('match_status', $request->match_status);
        }

        $transactions = $query->paginate(20)->withQueryString();

        $counters = [
            'total_debits' => $import->transactions()->where('type', 'debit')->count(),
            'matched' => $import->transactions()->whereIn('match_status', ['accepted', 'manual'])->count(),
            'pending' => $import->transactions()->where('match_status', 'pending')->count(),
            'unmatched' => $import->transactions()->where('match_status', 'unmatched')->count(),
            'rejected' => $import->transactions()->where('match_status', 'rejected')->count(),
        ];

        $alcada = app(PayableAlcadaService::class);
        $isConciliador = $alcada->isAssigned($request->user(), 'conciliador');

        return Inertia::render('BankConciliation/Show', [
            'import' => $import,
            'transactions' => $transactions,
            'counters' => $counters,
            'isConciliador' => $isConciliador,
            'filters' => $request->only(['match_status']),
        ]);
    }

    /**
     * Upload e processamento de arquivo OFX.
     */
    public function upload(
        Request $request,
        OfxParserService $parser,
        BankMatchingService $matcher,
        BankAccountMatcher $accountMatcher,
    ): RedirectResponse {
        $alcada = app(PayableAlcadaService::class);
        if (!$alcada->isAssigned($request->user(), 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
        ], [
            'file.max' => 'O arquivo não pode exceder 10MB.',
        ]);

        $file = $request->file('file');

        // Validate extension manually (.ofx)
        if (strtolower($file->getClientOriginalExtension()) !== 'ofx') {
            return back()->withErrors(['file' => 'O arquivo deve ter extensão .ofx.']);
        }
        $content = file_get_contents($file->getRealPath());

        // Parse OFX
        try {
            $result = $parser->parse($content);
        } catch (\App\Services\Ofx\OfxParseException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $bankAccountId = $request->filled('bank_account_id')
            ? (int) $request->bank_account_id
            : $accountMatcher->suggest($result->meta->bankId, $result->meta->accountId)?->id;

        // Create import record
        $import = BankStatementImport::create([
            'user_id' => $request->user()->id,
            'bank_account_id' => $bankAccountId,
            'bank_name' => $result->meta->orgName,
            'bank_id' => $result->meta->bankId,
            'account_number' => $result->meta->accountId ?? 'N/A',
            'branch_number' => $result->meta->branchId,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => '', // will update after storing
            'period_start' => $result->meta->periodStart,
            'period_end' => $result->meta->periodEnd,
            'balance' => $result->meta->balance,
            'status' => 'processing',
            'transaction_count' => count($result->transactions),
            'matched_count' => 0,
        ]);

        // Store file
        $path = $file->storeAs("ofx/{$import->id}", $file->getClientOriginalName(), 'local');
        $import->update(['file_path' => $path]);

        // Create transaction records
        foreach ($result->transactions as $index => $tx) {
            BankTransaction::create([
                'import_id' => $import->id,
                'fitid' => $tx->fitid ?: "import_{$import->id}_seq_{$index}",
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
        }

        // Run matching
        $matcher->run($import->id);

        // Update import status
        $matchedCount = $import->transactions()->whereIn('match_status', ['pending'])->where('match_confidence', '!=', 'none')->count();
        $import->update([
            'status' => 'done',
            'matched_count' => $matchedCount,
        ]);

        // Audit log
        AuditLogger::log(
            event: 'contas_pagar.ofx_importado',
            module: 'financeiro.contas_pagar',
            description: "Importação OFX: {$file->getClientOriginalName()} ({$result->meta->orgName} - {$result->meta->accountId}), {$import->transaction_count} transações",
            auditable: $import,
            newValues: [
                'bank_name' => $result->meta->orgName,
                'account_number' => $result->meta->accountId,
                'period_start' => $result->meta->periodStart?->toDateString(),
                'period_end' => $result->meta->periodEnd?->toDateString(),
                'transaction_count' => $import->transaction_count,
            ],
        );

        return redirect()->route('bank-conciliation.show', $import->id)
            ->with('success', "Extrato importado com sucesso: {$import->transaction_count} transações extraídas.");
    }

    /**
     * Aceitar um match sugerido.
     */
    public function accept(Request $request, int $id): RedirectResponse
    {
        $alcada = app(PayableAlcadaService::class);
        if (!$alcada->isAssigned($request->user(), 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }

        $transaction = BankTransaction::findOrFail($id);
        $transaction->update(['match_status' => 'accepted']);

        return back()->with('success', 'Match aceito.');
    }

    /**
     * Rejeitar um match sugerido.
     */
    public function reject(Request $request, int $id): RedirectResponse
    {
        $alcada = app(PayableAlcadaService::class);
        if (!$alcada->isAssigned($request->user(), 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }

        $transaction = BankTransaction::findOrFail($id);
        $transaction->update([
            'match_status' => 'rejected',
            'matched_payable_id' => null,
        ]);

        return back()->with('success', 'Match rejeitado.');
    }

    /**
     * Vincular manualmente um payable a uma transação.
     */
    public function link(Request $request, int $id): RedirectResponse
    {
        $alcada = app(PayableAlcadaService::class);
        if (!$alcada->isAssigned($request->user(), 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }

        $request->validate([
            'payable_id' => ['required', 'integer', 'exists:payables,id'],
        ]);

        $payable = Payable::findOrFail($request->payable_id);
        if ($payable->status !== 'pago') {
            return back()->withErrors(['payable_id' => 'O título deve estar com status "pago" para ser vinculado.']);
        }

        $transaction = BankTransaction::findOrFail($id);
        $transaction->update([
            'match_status' => 'manual',
            'matched_payable_id' => $payable->id,
        ]);

        return back()->with('success', 'Título vinculado manualmente.');
    }

    /**
     * Conciliar em lote os matches aceitos de uma importação.
     */
    public function batchConciliate(Request $request, int $importId, BatchConciliationService $batch): RedirectResponse
    {
        $result = $batch->execute($importId, $request->user());

        if (!empty($result['errors']) && $result['conciliated'] === 0) {
            return back()->with('error', $result['errors'][0] ?? 'Erro na conciliação em lote.');
        }

        $msg = "{$result['conciliated']} título(s) conciliado(s) com sucesso.";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} ignorado(s) (status incompatível).";
        }

        return back()->with('success', $msg);
    }

    /**
     * Excluir uma importação (somente se nenhuma transação aceita/conciliada).
     */
    public function destroy(Request $request, int $importId): RedirectResponse
    {
        $alcada = app(PayableAlcadaService::class);
        if (!$alcada->isAssigned($request->user(), 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }

        $import = BankStatementImport::findOrFail($importId);

        // Check if there are accepted or conciliated transactions
        $hasAcceptedOrConciliated = $import->transactions()
            ->whereIn('match_status', ['accepted', 'manual'])
            ->whereHas('matchedPayable', function ($q) {
                $q->where('status', 'conciliado');
            })
            ->exists();

        if ($hasAcceptedOrConciliated) {
            return back()->with('error', 'Não é possível excluir: há transações vinculadas a títulos já conciliados.');
        }

        // Audit before delete
        AuditLogger::log(
            event: 'contas_pagar.ofx_excluido',
            module: 'financeiro.contas_pagar',
            description: "Importação OFX excluída: {$import->file_name} ({$import->bank_name})",
            auditable: $import,
            oldValues: [
                'bank_name' => $import->bank_name,
                'account_number' => $import->account_number,
                'transaction_count' => $import->transaction_count,
            ],
        );

        // Delete file from storage
        if ($import->file_path) {
            Storage::disk('local')->delete($import->file_path);
        }

        // Cascade delete transactions (handled by FK constraint, but explicit for clarity)
        $import->delete();

        return redirect()->route('bank-conciliation.index')
            ->with('success', 'Importação excluída.');
    }

    /**
     * Buscar payables com status=pago para vinculação manual.
     */
    public function searchPayables(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2'],
        ]);

        $search = $request->input('query');
        $likeOp = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        $payables = Payable::query()
            ->where('status', 'pago')
            ->where(function ($q) use ($search, $likeOp) {
                $q->where('title_number', $likeOp, "%{$search}%")
                    ->orWhere('supplier_name', $likeOp, "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%");
            })
            ->select(['id', 'title_number', 'supplier_name', 'amount', 'paid_at', 'due_date'])
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get();

        return response()->json($payables);
    }
}
