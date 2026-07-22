<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BankConciliationController extends Controller
{
    /**
     * OFX-first workspace: recent day list + optional day report.
     */
    public function index(Request $request, ConciliationSessionService $sessions): Response
    {
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

        $days = $sessions->recentDaySummaries(14);

        $dayReport = null;
        if ($request->filled('date')) {
            $referenceDate = Carbon::parse($request->input('date'))->startOfDay();
            $dayReport = $sessions->dayReport($referenceDate);
        }

        return Inertia::render('BankConciliation/Index', [
            'isConciliador' => $isConciliador,
            'bankAccounts' => $bankAccounts,
            'days' => $days,
            'dayReport' => $dayReport,
            'filters' => [
                'date' => $request->input('date'),
            ],
            'importResults' => session('importResults'),
        ]);
    }

    /**
     * Detail of an import with paginated transactions + counters.
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
     * Upload a single OFX — date auto-detected from OFX content.
     */
    public function upload(
        Request $request,
        ConciliationSessionService $sessions,
    ): RedirectResponse {
        $this->ensureConciliador($request);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
        ], [
            'file.max' => 'O arquivo não pode exceder 10MB.',
        ]);

        $file = $request->file('file');
        if (strtolower($file->getClientOriginalExtension()) !== 'ofx') {
            return back()->withErrors(['file' => 'O arquivo deve ter extensão .ofx.']);
        }

        $bankAccountId = $request->filled('bank_account_id') ? (int) $request->input('bank_account_id') : null;
        $card = $this->importOneOfx($file, $request->user(), $sessions, $bankAccountId);

        $redirectDate = $card['ok'] ? ($card['date'] ?? null) : null;

        return redirect()->route('bank-conciliation.index', array_filter(['date' => $redirectDate]))
            ->with('importResults', [$card])
            ->with($card['ok'] ? 'success' : 'error', $card['ok']
                ? "Extrato importado: {$card['transaction_count']} transações."
                : ($card['error'] ?? 'Falha ao importar OFX.'));
    }

    /**
     * Batch upload multiple OFX files — date auto-detected per file.
     */
    public function uploadBatch(
        Request $request,
        ConciliationSessionService $sessions,
    ): RedirectResponse {
        $this->ensureConciliador($request);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:30'],
            'files.*' => ['file', 'max:10240'],
        ], [
            'files.max' => 'Envie no máximo 30 arquivos por vez.',
            'files.*.max' => 'Cada arquivo não pode exceder 10MB.',
        ]);

        $results = [];

        foreach ($request->file('files') as $file) {
            if (strtolower($file->getClientOriginalExtension()) !== 'ofx') {
                $results[] = [
                    'ok' => false,
                    'file_name' => $file->getClientOriginalName(),
                    'date' => null,
                    'bank_account_id' => null,
                    'bank_account_name' => null,
                    'account_created' => false,
                    'debit_count' => 0,
                    'credit_count' => 0,
                    'transaction_count' => 0,
                    'import_id' => null,
                    'error' => 'Extensão inválida (use .ofx).',
                ];
                continue;
            }

            $results[] = $this->importOneOfx($file, $request->user(), $sessions);
        }

        // Redirect to single date only when at least one file imported successfully
        $successDates = array_values(array_unique(array_filter(
            array_map(fn ($r) => $r['ok'] ? ($r['date'] ?? null) : null, $results)
        )));

        $okCount = count(array_filter($results, fn ($r) => $r['ok']));
        $redirectDate = count($successDates) === 1 ? $successDates[0] : null;

        $flashKey = $okCount > 0 ? 'success' : 'error';
        $flashMsg = $okCount > 0
            ? "{$okCount} extrato(s) importado(s)."
            : (($results[0]['error'] ?? null) ?: 'Nenhum arquivo OFX válido foi importado.');

        return redirect()->route('bank-conciliation.index', array_filter(['date' => $redirectDate]))
            ->with('importResults', $results)
            ->with($flashKey, $flashMsg);
    }

    /**
     * Accept a match — requires matched_payable_id.
     */
    public function accept(Request $request, int $id): RedirectResponse
    {
        $this->ensureConciliador($request);

        $transaction = BankTransaction::findOrFail($id);

        if (! $transaction->matched_payable_id) {
            return back()->withErrors(['transaction' => 'Esta transação não tem título vinculado. Use "Vincular" antes de aceitar.']);
        }

        $transaction->update(['match_status' => 'accepted']);

        return back()->with('success', 'Match aceito.');
    }

    /**
     * Reject a match — clears payable link and ambiguous flags.
     */
    public function reject(Request $request, int $id): RedirectResponse
    {
        $this->ensureConciliador($request);

        $transaction = BankTransaction::findOrFail($id);
        $transaction->update([
            // Volta para "Só OFX" — pode vincular de novo sem resetar o dia
            'match_status' => 'unmatched',
            'match_confidence' => 'none',
            'matched_payable_id' => null,
            'raw_data' => array_merge($transaction->raw_data ?? [], [
                'ambiguous' => false,
                'ambiguous_candidates' => [],
                'rejected_at' => now()->toIso8601String(),
            ]),
        ]);

        return back()->with('success', 'Match desfeito. O débito voltou para “Só no OFX” — você pode vincular outro título.');
    }

    /**
     * Link a transaction to a payable manually — resolves ambiguous.
     */
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
            'raw_data' => array_merge($transaction->raw_data ?? [], [
                'ambiguous' => false,
                'ambiguous_candidates' => [],
            ]),
        ]);

        return back()->with('success', 'Título vinculado manualmente.');
    }

    /**
     * Batch conciliate accepted transactions for a specific import.
     */
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

    /**
     * Batch conciliate all accepted transactions for a full day (all accounts).
     */
    public function batchConciliateDay(Request $request, BatchConciliationService $batch): RedirectResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $result = $batch->executeForDate($request->input('date'), $request->user());

        if (! empty($result['errors']) && $result['conciliated'] === 0) {
            return back()->with('error', $result['errors'][0] ?? 'Erro na conciliação em lote do dia.');
        }

        $msg = "{$result['conciliated']} título(s) conciliado(s) com sucesso.";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} ignorado(s).";
        }

        return back()->with('success', $msg);
    }

    /**
     * Apaga todos os extratos/sessões do dia para reimportar do zero.
     * Não altera títulos.
     */
    public function resetDay(Request $request, ConciliationSessionService $sessions): RedirectResponse
    {
        $this->ensureConciliador($request);

        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($request->input('date'))->startOfDay();

        try {
            $result = $sessions->resetDay($date, $request->user());
        } catch (\RuntimeException $e) {
            return redirect()->route('bank-conciliation.index', ['date' => $date->toDateString()])
                ->with('error', $e->getMessage());
        }

        if ($result['deleted_imports'] === 0) {
            return redirect()->route('bank-conciliation.index')
                ->with('warning', 'Nenhum extrato para resetar neste dia.')
                ->with('importResults', []);
        }

        return redirect()->route('bank-conciliation.index')
            ->with('success', "Dia {$date->format('d/m/Y')} resetado: {$result['deleted_imports']} extrato(s) removido(s). Pode importar de novo.")
            ->with('importResults', []);
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

        $redirectDate = null;
        if ($import->conciliationSession) {
            $redirectDate = $import->conciliationSession->reference_date->toDateString();
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

        return redirect()->route('bank-conciliation.index', array_filter(['date' => $redirectDate]))
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
            ->select(['id', 'title_number', 'supplier_name', 'amount', 'paid_at', 'due_date', 'codemp', 'codfil'])
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get();

        Payable::attachEmpresaNome($payables);
        Payable::attachFilialNome($payables);

        return response()->json($payables->map(fn (Payable $p) => [
            'id' => $p->id,
            'title_number' => $p->title_number,
            'supplier_name' => $p->supplier_name,
            'amount' => $p->amount,
            'paid_at' => $p->paid_at?->toDateString(),
            'due_date' => $p->due_date?->toDateString(),
            'empresa_nome' => $p->empresa_nome,
            'filial_label' => $p->filial_label,
        ]));
    }

    /**
     * Import a single OFX file, auto-detecting date and bank account from OFX content.
     *
     * @return array{ok: bool, file_name: string, date: ?string, bank_account_id: ?int, bank_account_name: ?string, debit_count: int, credit_count: int, transaction_count: int, import_id: ?int, error: ?string}
     */
    private function importOneOfx(
        UploadedFile $file,
        $user,
        ConciliationSessionService $sessions,
        ?int $bankAccountId = null,
    ): array {
        $fileName = $file->getClientOriginalName();

        try {
            /** @var OfxParserService $parser */
            $parser = app(OfxParserService::class);
            /** @var OfxImportService $importer */
            $importer = app(OfxImportService::class);

            $content = file_get_contents($file->getRealPath());
            $parsed = $parser->parse($content);

            // Auto-detect date — will throw if OFX covers multiple days
            $statementDate = $importer->assertSingleDay($parsed);

            $resolved = null;
            if ($bankAccountId !== null) {
                $forced = BankAccount::find($bankAccountId);
                if ($forced === null) {
                    return [
                        'ok' => false,
                        'file_name' => $fileName,
                        'date' => $statementDate->toDateString(),
                        'bank_account_id' => null,
                        'bank_account_name' => null,
                        'account_created' => false,
                        'debit_count' => 0,
                        'credit_count' => 0,
                        'transaction_count' => 0,
                        'import_id' => null,
                        'error' => 'Conta Hub informada não encontrada.',
                    ];
                }
                $resolved = ['account' => $forced, 'created' => false];
            } else {
                /** @var BankAccountMatcher $accountMatcher */
                $accountMatcher = app(BankAccountMatcher::class);
                $resolved = $accountMatcher->resolveOrCreate($parsed->meta, $user);
            }

            if ($resolved === null) {
                $acct = $parsed->meta->accountId ?? '?';

                return [
                    'ok' => false,
                    'file_name' => $fileName,
                    'date' => $statementDate->toDateString(),
                    'bank_account_id' => null,
                    'bank_account_name' => null,
                    'account_created' => false,
                    'debit_count' => 0,
                    'credit_count' => 0,
                    'transaction_count' => 0,
                    'import_id' => null,
                    'error' => "Não foi possível identificar a conta OFX {$acct}.",
                ];
            }

            $account = $resolved['account'];
            $accountCreated = $resolved['created'];
            $resolvedAccountId = $account->id;

            $session = $sessions->resolve($resolvedAccountId, $statementDate, $user);
            $skipFitIds = $sessions->existingFitIdsInSession($session);

            // Already-matched payable IDs for this day across all accounts
            $alreadyMatchedPayableIds = $sessions->matchedPayableIdsForDate($statementDate);

            $result = $importer->importFile(
                $file,
                $user,
                $resolvedAccountId,
                $session,
                $skipFitIds,
                $alreadyMatchedPayableIds,
            );

            $import = $result['import'];

            AuditLogger::log(
                event: 'contas_pagar.ofx_importado',
                module: 'financeiro.contas_pagar',
                description: "Importação OFX: {$fileName} ({$import->bank_name}), {$import->transaction_count} transações"
                    .($accountCreated ? ' — conta criada automaticamente' : ''),
                auditable: $import,
                newValues: [
                    'bank_name' => $import->bank_name,
                    'account_number' => $import->account_number,
                    'transaction_count' => $import->transaction_count,
                    'conciliation_session_id' => $session->id,
                    'bank_account_id' => $resolvedAccountId,
                    'account_created' => $accountCreated,
                    'balance' => $import->balance,
                    'statement_date' => $result['statement_date'],
                ],
            );

            if ($import->balance !== null) {
                AuditLogger::log(
                    event: 'financeiro.bancos.saldo_atualizado_ofx',
                    module: 'financeiro.bancos',
                    description: "Saldo da conta {$account->name} atualizado via OFX ({$fileName}): "
                        .number_format((float) $import->balance, 2, ',', '.'),
                    auditable: $account,
                    newValues: [
                        'balance' => $import->balance,
                        'balance_date' => $import->period_end?->toDateString() ?? $result['statement_date'],
                        'import_id' => $import->id,
                        'file_name' => $fileName,
                    ],
                );
            }

            return [
                'ok' => true,
                'file_name' => $fileName,
                'date' => $result['statement_date'],
                'bank_account_id' => $resolvedAccountId,
                'bank_account_name' => $account->name,
                'account_created' => $accountCreated,
                'debit_count' => $result['debit_count'],
                'credit_count' => $result['credit_count'],
                'transaction_count' => $import->transaction_count,
                'import_id' => $import->id,
                'error' => null,
            ];
        } catch (OfxParseException $e) {
            return [
                'ok' => false,
                'file_name' => $fileName,
                'date' => null,
                'bank_account_id' => null,
                'bank_account_name' => null,
                'account_created' => false,
                'debit_count' => 0,
                'credit_count' => 0,
                'transaction_count' => 0,
                'import_id' => null,
                'error' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'file_name' => $fileName,
                'date' => null,
                'bank_account_id' => null,
                'bank_account_name' => null,
                'account_created' => false,
                'debit_count' => 0,
                'credit_count' => 0,
                'transaction_count' => 0,
                'import_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function ensureConciliador(Request $request): void
    {
        $alcada = app(PayableAlcadaService::class);
        if (! $alcada->isAssigned($request->user(), 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador.');
        }
    }
}
