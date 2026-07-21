<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\ConciliationSession;
use App\Models\Payable;
use App\Services\AuditLogger;
use App\Services\BankAccountMatcher;
use App\Services\BatchConciliationService;
use App\Services\ConciliationSessionService;
use App\Services\OfxImportService;
use App\Services\OfxParserService;
use App\Services\Ofx\OfxParseException;
use App\Services\PayableAlcadaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BankConciliationController extends Controller
{
    /**
     * Workspace de conciliação por dia (conta + data).
     */
    public function index(Request $request, ConciliationSessionService $sessions): Response
    {
        $referenceDate = $sessions->parseReferenceDate(
            $request->input('date'),
            $request->filled('year') ? (int) $request->input('year') : null,
            $request->filled('month') ? (int) $request->input('month') : null,
            $request->filled('day') ? (int) $request->input('day') : null,
        );

        $bankAccountId = $request->filled('bank_account_id')
            ? (int) $request->input('bank_account_id')
            : null;

        $alcada = app(PayableAlcadaService::class);
        $isConciliador = $alcada->isAssigned($request->user(), 'conciliador');

        $bankAccounts = BankAccount::query()
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
            });

        $session = null;
        if ($bankAccountId !== null) {
            $sessionModel = $sessions->resolve($bankAccountId, $referenceDate, $request->user());
            $session = [
                'id' => $sessionModel->id,
                'bank_account_id' => $sessionModel->bank_account_id,
                'reference_date' => $sessionModel->reference_date->toDateString(),
                'period_label' => $sessionModel->periodLabel(),
                'status' => $sessionModel->status,
            ];
        }

        $summary = $sessions->summaryForDate($referenceDate, $bankAccountId);

        $pendingPayables = $sessions->pendingPayablesQuery($referenceDate)
            ->select(['id', 'title_number', 'supplier_name', 'amount', 'paid_at', 'due_date', 'status'])
            ->orderByDesc('paid_at')
            ->limit($bankAccountId === null ? 100 : 50)
            ->get()
            ->map(fn (Payable $p) => [
                'id' => $p->id,
                'title_number' => $p->title_number,
                'supplier_name' => $p->supplier_name,
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at?->toDateString(),
                'due_date' => $p->due_date?->toDateString(),
                'status' => $p->status,
            ]);

        $dateString = $referenceDate->toDateString();

        $importsQuery = BankStatementImport::query()
            ->with(['user:id,name', 'bankAccount:id,name,bank_code,account_number'])
            ->whereIn('conciliation_session_id', function ($q) use ($dateString, $bankAccountId) {
                $q->select('id')
                    ->from('conciliation_sessions')
                    ->whereDate('reference_date', $dateString);
                if ($bankAccountId !== null) {
                    $q->where('bank_account_id', $bankAccountId);
                }
            })
            ->orderByDesc('created_at');

        $imports = $importsQuery->paginate(15)->withQueryString();

        return Inertia::render('BankConciliation/Index', [
            'imports' => $imports,
            'isConciliador' => $isConciliador,
            'bankAccounts' => $bankAccounts,
            'filters' => [
                'bank_account_id' => $bankAccountId,
                'date' => $dateString,
            ],
            'session' => $session,
            'summary' => $summary,
            'periodLabel' => $sessions->periodLabel($referenceDate),
            'pendingPayables' => $pendingPayables,
        ]);
    }

    /**
     * Detalhe de uma importação com transações paginadas + contadores.
     */
    public function show(Request $request, int $importId): Response
    {
        $import = BankStatementImport::with(['user:id,name', 'conciliationSession.bankAccount:id,name'])
            ->findOrFail($importId);

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

        $sessionContext = null;
        if ($import->conciliationSession) {
            $sessionContext = [
                'bank_account_id' => $import->conciliationSession->bank_account_id,
                'date' => $import->conciliationSession->reference_date->toDateString(),
                'period_label' => $import->conciliationSession->periodLabel(),
            ];
        }

        return Inertia::render('BankConciliation/Show', [
            'import' => $import,
            'transactions' => $transactions,
            'counters' => $counters,
            'isConciliador' => $isConciliador,
            'filters' => $request->only(['match_status']),
            'sessionContext' => $sessionContext,
        ]);
    }

    /**
     * Upload de um arquivo OFX (mantido para compatibilidade).
     */
    public function upload(
        Request $request,
        OfxImportService $importer,
        ConciliationSessionService $sessions,
    ): RedirectResponse {
        $this->ensureConciliador($request);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'date' => ['required', 'date'],
        ], [
            'file.max' => 'O arquivo não pode exceder 10MB.',
        ]);

        $file = $request->file('file');
        if (strtolower($file->getClientOriginalExtension()) !== 'ofx') {
            return back()->withErrors(['file' => 'O arquivo deve ter extensão .ofx.']);
        }

        $referenceDate = Carbon::parse($request->date)->startOfDay();

        $session = $sessions->resolve(
            (int) $request->bank_account_id,
            $referenceDate,
            $request->user(),
        );

        [$paidFrom, $paidTo] = $session->periodBounds();

        try {
            $result = $importer->importFile(
                $file,
                $request->user(),
                (int) $request->bank_account_id,
                $session,
                $sessions->existingFitIdsInSession($session),
                $sessions->matchedPayableIdsInSession($session),
                $paidFrom,
                $paidTo,
            );
        } catch (OfxParseException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $import = $result['import'];

        AuditLogger::log(
            event: 'contas_pagar.ofx_importado',
            module: 'financeiro.contas_pagar',
            description: "Importação OFX: {$file->getClientOriginalName()} ({$import->bank_name}), {$import->transaction_count} transações",
            auditable: $import,
            newValues: [
                'bank_name' => $import->bank_name,
                'account_number' => $import->account_number,
                'transaction_count' => $import->transaction_count,
                'conciliation_session_id' => $session->id,
            ],
        );

        return redirect()->route('bank-conciliation.show', $import->id)
            ->with('success', "Extrato importado: {$import->transaction_count} transações extraídas.");
    }

    /**
     * Upload em lote de vários arquivos OFX para a competência selecionada.
     */
    public function uploadBatch(
        Request $request,
        OfxImportService $importer,
        ConciliationSessionService $sessions,
        OfxParserService $parser,
        BankAccountMatcher $accountMatcher,
    ): RedirectResponse {
        $this->ensureConciliador($request);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:30'],
            'files.*' => ['file', 'max:10240'],
            'date' => ['required', 'date'],
        ], [
            'files.max' => 'Envie no máximo 30 arquivos por vez.',
            'files.*.max' => 'Cada arquivo não pode exceder 10MB.',
        ]);

        $referenceDate = Carbon::parse($request->date)->startOfDay();

        $imported = 0;
        $transactions = 0;
        $skippedDuplicates = 0;
        $errors = [];
        $accountsUsed = [];

        foreach ($request->file('files') as $file) {
            if (strtolower($file->getClientOriginalExtension()) !== 'ofx') {
                $errors[] = "{$file->getClientOriginalName()}: extensão inválida (use .ofx).";
                continue;
            }

            try {
                $parsed = $parser->parse(file_get_contents($file->getRealPath()));
            } catch (OfxParseException $e) {
                $errors[] = "{$file->getClientOriginalName()}: {$e->getMessage()}";
                continue;
            }

            $account = $accountMatcher->suggest($parsed->meta->bankId, $parsed->meta->accountId);
            if ($account === null) {
                $acct = $parsed->meta->accountId ?? '?';
                $errors[] = "{$file->getClientOriginalName()}: conta OFX {$acct} não encontrada no cadastro Hub.";
                continue;
            }

            $session = $sessions->resolve($account->id, $referenceDate, $request->user());
            [$paidFrom, $paidTo] = $session->periodBounds();

            try {
                $result = $importer->importFile(
                    $file,
                    $request->user(),
                    $account->id,
                    $session,
                    $sessions->existingFitIdsInSession($session),
                    $sessions->matchedPayableIdsInSession($session),
                    $paidFrom,
                    $paidTo,
                );
            } catch (OfxParseException $e) {
                $errors[] = "{$file->getClientOriginalName()}: {$e->getMessage()}";
                continue;
            }

            $import = $result['import'];
            $imported++;
            $transactions += $import->transaction_count;
            $skippedDuplicates += $result['skipped_duplicates'];
            $accountsUsed[$account->id] = $account->name;

            AuditLogger::log(
                event: 'contas_pagar.ofx_importado',
                module: 'financeiro.contas_pagar',
                description: "Importação OFX (lote): {$file->getClientOriginalName()} → {$account->name}, {$import->transaction_count} transações",
                auditable: $import,
                newValues: [
                    'bank_account_id' => $account->id,
                    'bank_name' => $import->bank_name,
                    'transaction_count' => $import->transaction_count,
                    'conciliation_session_id' => $session->id,
                ],
            );
        }

        if ($imported === 0) {
            return back()->with('error', $errors[0] ?? 'Nenhum arquivo OFX válido foi importado.');
        }

        $msg = "{$imported} extrato(s) importado(s), {$transactions} transações.";
        if (count($accountsUsed) > 0) {
            $msg .= ' Contas: '.implode(', ', array_values($accountsUsed)).'.';
        }
        if ($skippedDuplicates > 0) {
            $msg .= " {$skippedDuplicates} duplicata(s) ignorada(s).";
        }
        if (! empty($errors)) {
            $msg .= ' Falhas: '.implode(' | ', array_slice($errors, 0, 3));
        }

        return redirect()->route('bank-conciliation.index', [
            'date' => $referenceDate->toDateString(),
        ])->with('success', $msg);
    }

    public function accept(Request $request, int $id): RedirectResponse
    {
        $this->ensureConciliador($request);

        $transaction = BankTransaction::findOrFail($id);
        $transaction->update(['match_status' => 'accepted']);

        return back()->with('success', 'Match aceito.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $this->ensureConciliador($request);

        $transaction = BankTransaction::findOrFail($id);
        $transaction->update([
            'match_status' => 'rejected',
            'matched_payable_id' => null,
        ]);

        return back()->with('success', 'Match rejeitado.');
    }

    public function link(Request $request, int $id): RedirectResponse
    {
        $this->ensureConciliador($request);

        $request->validate([
            'payable_id' => ['required', 'integer', 'exists:payables,id'],
        ]);

        $payable = Payable::findOrFail($request->payable_id);
        if (! in_array($payable->status, ['pago', 'aguardando_conciliacao'], true)) {
            return back()->withErrors(['payable_id' => 'O título deve estar pago ou aguardando conciliação.']);
        }

        $transaction = BankTransaction::findOrFail($id);
        $transaction->update([
            'match_status' => 'manual',
            'matched_payable_id' => $payable->id,
        ]);

        return back()->with('success', 'Título vinculado manualmente.');
    }

    public function batchConciliate(Request $request, int $importId, BatchConciliationService $batch): RedirectResponse
    {
        $result = $batch->execute($importId, $request->user());

        if (! empty($result['errors']) && $result['conciliated'] === 0) {
            return back()->with('error', $result['errors'][0] ?? 'Erro na conciliação em lote.');
        }

        $msg = "{$result['conciliated']} título(s) conciliado(s) com sucesso.";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} ignorado(s) (status incompatível).";
        }

        return back()->with('success', $msg);
    }

    public function destroy(Request $request, int $importId): RedirectResponse
    {
        $this->ensureConciliador($request);

        $import = BankStatementImport::findOrFail($importId);

        $hasAcceptedOrConciliated = $import->transactions()
            ->whereIn('match_status', ['accepted', 'manual'])
            ->whereHas('matchedPayable', function ($q) {
                $q->where('status', 'conciliado');
            })
            ->exists();

        if ($hasAcceptedOrConciliated) {
            return back()->with('error', 'Não é possível excluir: há transações vinculadas a títulos já conciliados.');
        }

        $redirectParams = [];
        if ($import->conciliationSession) {
            $redirectParams = [
                'bank_account_id' => $import->conciliationSession->bank_account_id,
                'date' => $import->conciliationSession->reference_date->toDateString(),
            ];
        }

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

        if ($import->file_path) {
            Storage::disk('local')->delete($import->file_path);
        }

        $import->delete();

        return redirect()->route('bank-conciliation.index', $redirectParams)
            ->with('success', 'Importação excluída.');
    }

    public function searchPayables(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2'],
            'date' => ['nullable', 'date'],
        ]);

        $search = $request->input('query');
        $likeOp = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        $query = Payable::query()
            ->whereIn('status', ['pago', 'aguardando_conciliacao'])
            ->where(function ($q) use ($search, $likeOp) {
                $q->where('title_number', $likeOp, "%{$search}%")
                    ->orWhere('supplier_name', $likeOp, "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%");
            });

        if ($request->filled('date')) {
            $query->whereNotNull('paid_at')
                ->whereDate('paid_at', Carbon::parse($request->date)->toDateString());
        }

        $payables = $query
            ->select(['id', 'title_number', 'supplier_name', 'amount', 'paid_at', 'due_date'])
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get();

        return response()->json($payables);
    }

    private function ensureConciliador(Request $request): void
    {
        $alcada = app(PayableAlcadaService::class);
        if (! $alcada->isAssigned($request->user(), 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }
    }
}
