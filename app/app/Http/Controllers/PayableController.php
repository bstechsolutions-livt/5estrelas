<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayableRequest;
use App\Models\Branch;
use App\Models\Comercial\Filial as ComercialFilial;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\PayableDocument;
use App\Models\PayableSyncRun;
use App\Models\ApprovalStep;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ApprovalWorkflowService;
use App\Services\FinanceiroDepartmentScope;
use App\Services\PayableAlcadaService;
use App\Services\PayableBranchScope;
use App\Services\PayableDepartmentClassifier;
use App\Services\PayableAllocationImportService;
use App\Services\PayableDocumentPairAlert;
use App\Support\FilterDate;
use App\Support\PayableApprovalDeadline;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PayableController extends Controller
{
    private const ALLOCATION_IMPORT_STATUSES = [
        'pendente', 'em_preparacao', 'aguardando_aprovacao', 'aprovado',
        'pago', 'aguardando_conciliacao', 'conciliado', 'divergente',
    ];

    public function index(Request $request)
    {
        $pageData = $this->resolvePayablesPageData($request, defaultPerPage: 20);

        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($pageData['payables']);
        }

        return Inertia::render('Payables/Index', $this->payablesIndexProps($request, $pageData));
    }

    public function updateNickname(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);
        $user = $request->user();

        $data = $request->validate([
            'nickname' => ['nullable', 'string', 'max:120'],
        ]);

        $nickname = filled($data['nickname'] ?? null) ? trim($data['nickname']) : null;
        $old = $payable->nickname;
        $payable->update(['nickname' => $nickname]);

        if ($old !== $nickname) {
            AuditLogger::log(
                event: 'contas_pagar.apelido_atualizado',
                module: 'financeiro.contas_pagar',
                description: $nickname
                    ? "Apelido do título {$payable->title_number} definido como \"{$nickname}\""
                    : "Apelido removido do título {$payable->title_number}",
                auditable: $payable,
                oldValues: ['nickname' => $old],
                newValues: ['nickname' => $nickname],
            );
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'nickname' => $nickname]);
        }

        return back()->with('success', $nickname ? 'Apelido salvo.' : 'Apelido removido.');
    }

    public function batchUpdateNicknames(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.id' => ['required', 'integer'],
            'items.*.nickname' => ['nullable', 'string', 'max:120'],
        ]);

        $updated = 0;

        foreach ($data['items'] as $item) {
            $payable = $this->findPayableForUser((int) $item['id']);
            $nickname = filled($item['nickname'] ?? null) ? trim($item['nickname']) : null;

            if ($payable->nickname === $nickname) {
                continue;
            }

            $payable->update(['nickname' => $nickname]);
            $updated++;
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'updated' => $updated]);
        }

        return back()->with('success', $updated > 0
            ? "{$updated} apelido(s) atualizado(s)."
            : 'Nenhuma alteração para salvar.');
    }

    public function batchSendForApproval(Request $request)
    {
        $data = $request->validate([
            'payable_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'payable_ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $workflow = app(ApprovalWorkflowService::class);

        $urgent = $request->boolean('urgente');
        $sent = 0;
        $skipped = 0;
        $errors = [];

        foreach ($data['payable_ids'] as $id) {
            try {
                $payable = $this->findPayableForUser((int) $id, $user);

                if (! in_array($payable->status, ['pendente', 'em_preparacao'], true)) {
                    $skipped++;
                    continue;
                }
                if ($payable->bordero_id) {
                    $skipped++;
                    continue;
                }
                if ($payable->documents()->count() === 0) {
                    $skipped++;
                    continue;
                }

                $preview = $workflow->buildPreviewStepsForPayable($payable);
                if (! $preview['ok']) {
                    $errors[] = $preview['errors'][0] ?? "Título #{$id}: fluxo inválido.";
                    continue;
                }

                $deadline = PayableApprovalDeadline::validateForSend($payable, $user, $urgent);
                if (! $deadline['ok']) {
                    $errors[] = $deadline['error'];
                    continue;
                }

                $workflow->sendForApproval($payable, $user);
                if ($deadline['bypassed']) {
                    PayableApprovalDeadline::logUrgentSend($payable, $user);
                }
                $sent++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($sent === 0) {
            return back()->with('error', $errors[0] ?? 'Nenhum título elegível para envio (verifique documentos, status e borderô).');
        }

        $message = "{$sent} título(s) enviado(s) para aprovação.";
        if ($skipped > 0) {
            $message .= " {$skipped} ignorado(s).";
        }

        return back()->with('success', $message);
    }

    public function batchApprove(Request $request)
    {
        $data = $request->validate([
            'payable_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'payable_ids.*' => ['integer'],
            'comment' => ['nullable', 'string', 'max:500'],
            'payment_priority' => ['nullable', Rule::in(Payable::PRIORITY_VALUES)],
            'payment_sla_date' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $workflow = app(ApprovalWorkflowService::class);

        $payables = collect($data['payable_ids'])
            ->map(fn ($id) => $this->findPayableForUser((int) $id, $user))
            ->filter(fn (Payable $payable) => $payable->status === 'aguardando_aprovacao' && ! $payable->bordero_id)
            ->filter(fn (Payable $payable) => $payable->documents()->count() > 0)
            ->values();

        if ($payables->isEmpty()) {
            return back()->with('error', 'Nenhum título selecionado está aguardando aprovação.');
        }

        $sample = $payables->first(fn (Payable $payable) => $workflow->canUserApprove($payable, $user));
        if ($sample && $workflow->isFinanceStep($workflow->currentStep($sample))) {
            $priorityData = $request->validate([
                'payment_priority' => ['required', Rule::in(Payable::PRIORITY_VALUES)],
                'payment_sla_date' => ['nullable', 'date'],
            ]);

            foreach ($payables as $payable) {
                if (! $workflow->canUserApprove($payable, $user)) {
                    continue;
                }
                $this->applyPaymentPriority($payable, $user, $priorityData);
            }
        }

        $result = $workflow->approveEligibleInBordero($payables, $user, $data['comment'] ?? null);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', "{$result['count']} título(s) aprovado(s). {$result['message']}");
    }

    public function batchUpdatePriority(Request $request)
    {
        $user = $request->user();

        if (! $user?->hasPermission('financeiro.contas_pagar.prioridade_gerenciar')) {
            abort(403, 'Você não tem permissão para alterar a prioridade de pagamento.');
        }

        $data = $request->validate([
            'payable_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'payable_ids.*' => ['integer'],
            'payment_priority' => ['required', Rule::in(Payable::PRIORITY_VALUES)],
            'payment_sla_date' => ['nullable', 'date'],
        ]);

        $updated = 0;

        foreach ($data['payable_ids'] as $id) {
            $payable = $this->findPayableForUser((int) $id, $user);

            if ($payable->status === 'encerrado') {
                continue;
            }

            $this->applyPaymentPriority($payable, $user, $data);
            $updated++;
        }

        return back()->with('success', $updated > 0
            ? "Prioridade atualizada em {$updated} título(s)."
            : 'Nenhum título elegível para alteração de prioridade.');
    }

    /** @return array<string, mixed> */
    private function payablesIndexProps(Request $request, array $pageData): array
    {
        $user = $request->user();
        $branchScope = app(PayableBranchScope::class)->resolve($user);

        return [
            'payables' => $pageData['payables'],
            'totals' => $pageData['totals'],
            'filters' => array_merge(
                $request->only(['search', 'due_from', 'due_to', 'issue_from', 'issue_to', 'codemp', 'filial', 'branch_id', 'amount_min', 'amount_max', 'payment_priority', 'sort', 'dir', 'per_page']),
                [
                    'status' => $pageData['status'],
                    'department_id' => $pageData['departmentContext']['department_id'],
                ],
            ),
            'empresas' => app(PayableBranchScope::class)->empresaOptionsForUser($user),
            'filiais' => app(PayableBranchScope::class)->filialOptionsForUser($user),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'statusOptions' => Payable::STATUS_LABELS,
            'canChangeDepartmentFilter' => $pageData['departmentContext']['can_change'],
            'lockedDepartment' => $pageData['departmentContext']['locked_department'],
            'lockedBranches' => $branchScope['locked_branches'],
            'noBranchAccess' => $branchScope['no_branch_access'],
            'canManageClassification' => $user?->hasPermission('financeiro.contas_pagar.classificacao_gerenciar') ?? false,
            'canManagePriority' => $user?->hasPermission('financeiro.contas_pagar.prioridade_gerenciar') ?? false,
            'canLancar' => $user?->hasPermission('financeiro.contas_pagar.lancar') ?? false,
            'priorityOptions' => Payable::PRIORITY_LABELS,
            'canBypassApprovalDeadline' => PayableApprovalDeadline::canBypass($user),
            'minDueDateForApproval' => PayableApprovalDeadline::minDueDateForApproval()->toDateString(),
            'syncStatus' => $this->payablesSyncStatusProps($user),
        ];
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $branchScope = app(PayableBranchScope::class)->resolve($user);
        $deptScope = app(FinanceiroDepartmentScope::class);
        $canChangeDepartment = $deptScope->canBypass($user);
        $lockedDepartmentId = $canChangeDepartment ? null : $deptScope->resolve($user);
        $lockedDepartment = $lockedDepartmentId
            ? Department::whereKey($lockedDepartmentId)->first(['id', 'name'])
            : null;

        $filiais = app(PayableBranchScope::class)->filialOptionsForUser($user);
        $defaultFilial = count($filiais) === 1 ? $filiais[0]['value'] : null;

        return Inertia::render('Payables/Create', [
            'filiais' => $filiais,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canChangeDepartment' => $canChangeDepartment,
            'lockedDepartment' => $lockedDepartment ? ['id' => $lockedDepartment->id, 'name' => $lockedDepartment->name] : null,
            'defaultFilial' => $defaultFilial,
            'defaultDueDate' => Payable::defaultDueDate()->toDateString(),
            'priorityOptions' => Payable::PRIORITY_LABELS,
            'noBranchAccess' => $branchScope['no_branch_access'],
        ]);
    }

    public function store(StorePayableRequest $request)
    {
        $attrs = $request->payableAttributes();
        $user = $request->user();

        $payable = Payable::create(array_merge($attrs, [
            'status' => 'pendente',
            'prepared_by' => $user->id,
            'senior_id' => null,
        ]));

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $user->id,
            'body' => 'Título lançado manualmente pela intranet (Hub).',
            'type' => 'status_change',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.lancado_manual',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} lançado manualmente - {$payable->supplier_name} R$ {$payable->amount}",
            auditable: $payable,
            newValues: [
                'origem' => 'hub',
                'supplier_name' => $payable->supplier_name,
                'amount' => $payable->amount,
                'due_date' => $payable->due_date?->toDateString(),
                'codemp' => $payable->codemp,
                'codfil' => $payable->codfil,
            ],
        );

        return redirect()
            ->route('payables.show', $payable->id)
            ->with('success', 'Título lançado. Anexe documentos e envie para aprovação quando estiver pronto.');
    }

    /**
     * Resumo leve do sync Senior p/ banner da lista (sem histórico).
     *
     * @return array{
     *   last_finished_at: ?string,
     *   next_estimated_at: ?string,
     *   online: ?bool,
     *   status_label: ?string,
     *   sync_interval_minutes: int,
     *   can_view_monitor: bool
     * }
     */
    private function payablesSyncStatusProps(?User $user): array
    {
        $interval = max(1, (int) config('senior.sync_interval_minutes', 5));
        $tz = 'America/Sao_Paulo';
        $now = Carbon::now($tz);

        $canViewMonitor = $user?->hasPermission('financeiro.workflows.configurar') ?? false;

        $empty = [
            'last_finished_at' => null,
            'next_estimated_at' => $this->nextSyncSlotAfter($now, $interval)->toIso8601String(),
            'online' => null,
            'status_label' => null,
            'sync_interval_minutes' => $interval,
            'can_view_monitor' => $canViewMonitor,
        ];

        if (! Schema::hasTable('payable_sync_runs')) {
            return $empty;
        }

        $lastClosed = PayableSyncRun::query()
            ->whereIn('status', [
                PayableSyncRun::STATUS_SUCCESS,
                PayableSyncRun::STATUS_FAILED,
            ])
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first(['status', 'finished_at', 'started_at']);

        if (! $lastClosed) {
            return $empty;
        }

        $finishedAt = ($lastClosed->finished_at ?? $lastClosed->started_at)?->copy()->timezone($tz);
        $online = $lastClosed->status === PayableSyncRun::STATUS_SUCCESS;

        $next = $finishedAt
            ? $finishedAt->copy()->addMinutes($interval)
            : null;

        if (! $next || $next->lte($now)) {
            $next = $this->nextSyncSlotAfter($now, $interval);
        }

        return [
            'last_finished_at' => $finishedAt?->toIso8601String(),
            'next_estimated_at' => $next->toIso8601String(),
            'online' => $online,
            'status_label' => $online ? 'Online' : 'Offline',
            'sync_interval_minutes' => $interval,
            'can_view_monitor' => $canViewMonitor,
        ];
    }

    /** Próximo horário alinhado ao intervalo (ex.: a cada 5 min a partir de agora). */
    private function nextSyncSlotAfter(Carbon $from, int $intervalMinutes): Carbon
    {
        $slot = $from->copy()->timezone('America/Sao_Paulo')->startOfMinute();
        $minute = (int) $slot->format('i');
        $remainder = $minute % $intervalMinutes;

        if ($remainder === 0 && (int) $from->format('s') === 0) {
            return $slot->copy()->addMinutes($intervalMinutes);
        }

        $add = $remainder === 0 ? $intervalMinutes : ($intervalMinutes - $remainder);

        return $slot->copy()->addMinutes($add)->seconds(0);
    }

    /**
     * @return array{
     *   payables: \Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *   status: string,
     *   departmentContext: array{department_id: ?int, can_change: bool, locked_department: ?array},
     *   totals: \Illuminate\Support\Collection
     * }
     */
    private function resolvePayablesPageData(Request $request, int $defaultPerPage = 20): array
    {
        $user = $request->user();
        $departmentContext = app(FinanceiroDepartmentScope::class)->resolveFilter($request);

        $query = Payable::query()
            ->excludeMissingInSenior()
            ->with(['branch:id,name', 'preparer:id,name', 'bordero:id,number']);

        $status = $request->input('status') ?: 'pendente';
        $query->where('status', $status);

        if ($status === 'pendente') {
            $query->whereNull('bordero_id');
        }

        $this->applyPayablesListFilters($query, $request, $departmentContext['department_id'], $user);
        $this->applyPayablesOrdering($query, $request->input('sort'), $request->input('dir'));

        $payables = $query->paginate($this->resolvePerPage($request, $defaultPerPage))->withQueryString();

        Payable::attachEmpresaNome($payables->getCollection());
        Payable::attachFilialNome($payables->getCollection());
        Payable::attachSupplierDisplayName($payables->getCollection());
        Payable::attachDepartmentNome($payables->getCollection());
        PayableDocumentPairAlert::attachToPayables($payables->getCollection());
        Payable::attachOrigemHub($payables->getCollection());
        Payable::attachOrigemSenior($payables->getCollection());
        Payable::attachPriorityMeta($payables->getCollection());
        Payable::attachWorkflowMoment($payables->getCollection());

        // Totals das abas: mesmos filtros da lista (exceto status da aba ativa).
        $totalsQuery = Payable::query()
            ->excludeMissingInSenior()
            ->where(function ($q) {
                $q->where('status', '!=', 'pendente')
                    ->orWhereNull('bordero_id');
            });
        $this->applyPayablesListFilters($totalsQuery, $request, $departmentContext['department_id'], $user);
        $totals = $totalsQuery
            ->selectRaw('status, count(*) as count, coalesce(sum(amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'payables' => $payables,
            'status' => $status,
            'departmentContext' => $departmentContext,
            'totals' => $totals,
        ];
    }

    /**
     * Filtros compartilhados entre a listagem e as contagens das abas de status.
     * Não aplica o status da aba (isso fica a cargo do caller).
     */
    private function applyPayablesListFilters(
        \Illuminate\Database\Eloquent\Builder $query,
        Request $request,
        ?int $departmentId,
        ?User $user,
    ): void {
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('supplier_name', 'ilike', "%{$s}%")
                    ->orWhere('title_number', 'ilike', "%{$s}%")
                    ->orWhere('nickname', 'ilike', "%{$s}%")
                    ->orWhere('description', 'ilike', "%{$s}%");
            });
        }
        $dueFrom = FilterDate::parse($request->input('due_from'));
        $dueTo = FilterDate::parse($request->input('due_to'));
        if ($dueFrom) {
            $query->whereDate('due_date', '>=', $dueFrom);
        }
        if ($dueTo) {
            $query->whereDate('due_date', '<=', $dueTo);
        }
        $issueFrom = FilterDate::parse($request->input('issue_from'));
        $issueTo = FilterDate::parse($request->input('issue_to'));
        if ($issueFrom) {
            $query->whereDate('issue_date', '>=', $issueFrom);
        }
        if ($issueTo) {
            $query->whereDate('issue_date', '<=', $issueTo);
        }
        if ($request->filled('filial')) {
            $pair = $this->parseFilialFilter((string) $request->filial);
            if ($pair !== null) {
                $query->where('codemp', $pair[0])->where('codfil', $pair[1]);
            }
        } elseif ($request->filled('codemp')) {
            $query->where('codemp', (int) $request->codemp);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', (float) $request->amount_min);
        }
        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', (float) $request->amount_max);
        }
        if ($request->filled('payment_priority')) {
            if ($request->payment_priority === 'sem') {
                $query->whereNull('payment_priority');
            } else {
                $query->where('payment_priority', $request->payment_priority);
            }
        }
        app(FinanceiroDepartmentScope::class)->applyFilter($query, $departmentId);
        app(PayableBranchScope::class)->applyFilter($query, $user);
    }

    private function resolvePerPage(Request $request, int $default): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return max(1, min(1000, $perPage));
    }

    private function applyDepartmentScope(\Illuminate\Database\Eloquent\Builder $query, ?int $departmentId): void
    {
        app(FinanceiroDepartmentScope::class)->applyFilter($query, $departmentId);
    }

    private function applyPayablesOrdering(\Illuminate\Database\Eloquent\Builder $query, ?string $sort, ?string $dir): void
    {
        $allowed = [
            'due_date',
            'amount',
            'supplier_name',
            'title_number',
            'nickname',
            'description',
            'payment_sla_date',
            'payment_priority',
            'department_id',
            'department_nome',
            'codemp',
        ];

        if (empty($sort) || $sort === 'default' || ! in_array($sort, $allowed, true)) {
            $query->orderByRaw("CASE COALESCE(payment_priority, '') WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                ->orderBy('payment_sla_date')
                ->orderByDesc('due_date');

            return;
        }

        $direction = strtolower((string) $dir) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'payment_priority') {
            if ($direction === 'desc') {
                $query->orderByRaw("CASE COALESCE(payment_priority, '') WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END DESC");
            } else {
                $query->orderByRaw("CASE COALESCE(payment_priority, '') WHEN '' THEN 1 WHEN 'normal' THEN 2 WHEN 'alta' THEN 3 WHEN 'urgente' THEN 4 ELSE 5 END");
            }

            return;
        }

        if ($sort === 'department_nome') {
            $query->leftJoin('departments', 'payables.department_id', '=', 'departments.id')
                ->orderBy('departments.name', $direction)
                ->select('payables.*');

            return;
        }

        $query->orderBy($sort, $direction);
    }

    /** @return array{0:int,1:int}|null */
    private function parseFilialFilter(string $value): ?array
    {
        if (! preg_match('/^(\d+)-(\d+)$/', trim($value), $matches)) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function findPayableForUser(int $id, ?User $user = null): Payable
    {
        $payable = Payable::findOrFail($id);
        $user = $user ?? request()->user();

        if ($user && !app(PayableBranchScope::class)->canAccessPayable($user, $payable)) {
            $scope = app(PayableBranchScope::class)->resolve($user);
            abort(403, $scope['no_branch_access']
                ? PayableBranchScope::NO_BRANCH_ACCESS_MESSAGE
                : 'Você não tem acesso a títulos desta filial.');
        }

        return $payable;
    }

    public function show(int $id, PayableAlcadaService $alcada)
    {
        $payable = $this->findPayableForUser($id);
        $payable->load([
            'branch:id,name',
            'preparer:id,name',
            'approver:id,name',
            'payer:id,name',
            'conciliator:id,name',
            'prioritySetter:id,name',
            'documents.uploader:id,name',
            'comments.user:id,name,avatar_path',
            'allocationLines',
            'allocationImporter:id,name',
        ]);

        $user = request()->user();
        $isPagador = $user ? $alcada->isAssigned($user, 'pagador') : false;
        $isConciliador = $user ? $alcada->isAssigned($user, 'conciliador') : false;

        // A3: nome da empresa (nunca código) também no detalhe.
        Payable::attachEmpresaNome([$payable]);
        Payable::attachFilialNome([$payable]);
        Payable::attachSupplierDisplayName([$payable]);
        Payable::attachDepartmentNome([$payable]);
        Payable::attachAccountingLabels([$payable]);
        $payable->setAttribute(
            'document_pair_alert',
            PayableDocumentPairAlert::resolveFromDocuments($payable->documents, $payable->status),
        );
        if ($payable->isHubManual()) {
            $payable->setAttribute('origem_hub', true);
        } else {
            $payable->setAttribute('origem_senior', true);
            Payable::attachFieldOrigins($payable);
        }
        Payable::attachPriorityMeta([$payable]);

        // Approval steps (workflow multinível)
        $approvalSteps = ApprovalStep::where('payable_id', $id)
            ->with(['assignee:id,name', 'resolver:id,name', 'delegatee:id,name', 'delegationSetBy:id,name'])
            ->orderBy('order')
            ->get();

        $workflow = app(ApprovalWorkflowService::class);
        $currentStep = $workflow->currentStep($payable);
        $canApproveStep = $workflow->canUserApprove($payable, $user);
        $canDelegateStep = $currentStep
            && $workflow->canManageStepDelegation($user, $currentStep, $payable);
        $delegateUsers = ($canDelegateStep || $user?->hasPermission('financeiro.workflows.delegar') || $user?->hasPermission('*'))
            ? $workflow->delegateCandidateUsers()
            : collect();

        return Inertia::render('Payables/Show', [
            'payable' => $payable,
            'statusLabels' => Payable::STATUS_LABELS,
            'statusColors' => Payable::STATUS_COLORS,
            'priorityLabels' => Payable::PRIORITY_LABELS,
            'priorityColors' => Payable::PRIORITY_COLORS,
            'canPay' => $isPagador && $payable->status === 'aprovado',
            'paymentMethods' => Payable::PAYMENT_METHODS,
            'pagadorConfigured' => $alcada->hasRole('pagador'),
            'canConciliate' => $isConciliador && in_array($payable->status, ['pago', 'aguardando_conciliacao'], true),
            'conciliadorConfigured' => $alcada->hasRole('conciliador'),
            'canFinalSign' => $alcada->isAssigned($user, 'assinante') && $payable->status === 'conciliado',
            'canEditDueDate' => $user?->hasPermission('financeiro.contas_pagar.editar_vencimento') ?? false,
            'canManagePriority' => $user?->hasPermission('financeiro.contas_pagar.prioridade_gerenciar') ?? false,
            'requiresPriorityOnApprove' => $canApproveStep && $workflow->isFinanceStep($currentStep),
            'approvalSteps' => $approvalSteps,
            'currentStep' => $currentStep,
            'canApproveStep' => $canApproveStep,
            'canDelegateStep' => $canDelegateStep,
            'delegateUsers' => $delegateUsers,
            'mentionableUsers' => app(\App\Services\MentionService::class)->mentionableUsers($user, $id),
            'approvalPreview' => $workflow->buildPreviewStepsForPayable($payable),
            'canImportAllocations' => in_array($payable->status, self::ALLOCATION_IMPORT_STATUSES, true),
            'canBypassApprovalDeadline' => PayableApprovalDeadline::canBypass($user),
            'minDueDateForApproval' => PayableApprovalDeadline::minDueDateForApproval()->toDateString(),
        ]);
    }

    public function importAllocations(Request $request, int $id, PayableAllocationImportService $importService)
    {
        $payable = $this->findPayableForUser($id);

        if (! in_array($payable->status, self::ALLOCATION_IMPORT_STATUSES, true)) {
            return back()->with('error', 'Rateio por planilha não permitido neste status do título.');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $result = $importService->import($payable, $request->file('file'), $request->user()->id);

        $message = sprintf(
            'Rateio importado: %d linha(s), total R$ %s.',
            $result['lines'],
            number_format($result['total'], 2, ',', '.'),
        );

        if ($result['warnings'] !== []) {
            return back()
                ->with('success', $message)
                ->with('warning', implode(' ', $result['warnings']));
        }

        return back()->with('success', $message);
    }

    public function addComment(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'type' => 'comment',
        ]);

        // Processa @menções (notificação + visibilidade)
        app(\App\Services\MentionService::class)->processComment($comment);

        // Se estava pendente, marca como em preparação
        if ($payable->status === 'pendente') {
            $payable->update([
                'status' => 'em_preparacao',
                'prepared_by' => $request->user()->id,
            ]);
        }

        return back();
    }

    public function addDocument(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);

        $request->validate([
            'file' => ['nullable', 'file', 'max:10240'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
            'type' => ['nullable', Rule::in(array_keys(PayableDocument::TYPES))],
        ]);

        $files = $request->hasFile('files')
            ? $request->file('files')
            : ($request->hasFile('file') ? [$request->file('file')] : []);

        if ($files === []) {
            return back()->with('error', 'Nenhum arquivo enviado.');
        }

        $type = $request->input('type', 'outro');
        $user = $request->user();
        $names = [];

        foreach ($files as $file) {
            $path = $file->store('payables/docs', 'public');

            PayableDocument::create([
                'payable_id' => $payable->id,
                'uploaded_by' => $user->id,
                'name' => $file->getClientOriginalName(),
                'doc_type' => $type,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            $names[] = $file->getClientOriginalName();
        }

        if ($payable->status === 'pendente') {
            $payable->update([
                'status' => 'em_preparacao',
                'prepared_by' => $user->id,
            ]);
        }

        $typeLabel = PayableDocument::TYPES[$type] ?? $type;
        $list = implode(', ', $names);
        $body = count($names) === 1
            ? "Anexou documento: {$list}"
            : 'Anexou ' . count($names) . " documentos ({$typeLabel}): {$list}";

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $user->id,
            'body' => $body,
            'type' => 'status_change',
        ]);

        return back();
    }

    public function sendForApproval(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);

        if (!in_array($payable->status, ['pendente', 'em_preparacao'])) {
            return back()->with('error', 'Este título não pode ser enviado para aprovação.');
        }

        // Trava (feedback do cliente): não pode ir pra aprovação sem NENHUM documento
        // anexado (nota fiscal, boleto, relatório ou comprovante). Nem todo título tem
        // tudo, mas não pode ser aprovado "sem nada".
        if ($payable->documents()->count() === 0) {
            return back()->with('error', 'Anexe ao menos um documento (nota fiscal, boleto, relatório ou comprovante) antes de enviar para aprovação.');
        }

        $workflow = app(ApprovalWorkflowService::class);
        $preview = $workflow->buildPreviewStepsForPayable($payable);
        if (! $preview['ok']) {
            return back()->with('error', $preview['errors'][0] ?? 'Não foi possível enviar para aprovação.');
        }

        $urgent = $request->boolean('urgente');
        $deadline = PayableApprovalDeadline::validateForSend($payable, $request->user(), $urgent);
        if (! $deadline['ok']) {
            return back()->with('error', $deadline['error']);
        }

        try {
            $workflow->sendForApproval($payable, $request->user());
            if ($deadline['bypassed']) {
                PayableApprovalDeadline::logUrgentSend($payable, $request->user());
            }
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Enviado para aprovação multinível.');
    }

    public function approve(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);

        if ($payable->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este título não está aguardando aprovação.');
        }

        // Trava (feedback do cliente): não é possível aprovar um título sem documentos.
        // Guarda defensiva — um documento pode ter sido removido depois do envio.
        if ($payable->documents()->count() === 0) {
            return back()->with('error', 'Não é possível aprovar um título sem documentos anexados.');
        }

        $workflow = app(ApprovalWorkflowService::class);
        $currentStep = $workflow->currentStep($payable);
        if ($workflow->isFinanceStep($currentStep)) {
            $priorityData = $request->validate([
                'payment_priority' => ['required', Rule::in(Payable::PRIORITY_VALUES)],
                'payment_sla_date' => ['nullable', 'date'],
            ]);
            $this->applyPaymentPriority($payable, $request->user(), $priorityData);
        }

        $result = $workflow->approve($payable, $request->user(), $request->input('comment'));

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['message']);
    }

    public function reject(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);

        if ($payable->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este título não está aguardando aprovação.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $workflow = app(ApprovalWorkflowService::class);
        $result = $workflow->reject($payable, $request->user(), $data['reason']);

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['message']);
    }

    public function delegateApprovalStep(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);

        if ($payable->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este título não está aguardando aprovação.');
        }

        $data = $request->validate([
            'step_id' => ['required', 'integer', 'exists:approval_steps,id'],
            'delegate_user_id' => ['required', 'integer', 'exists:users,id'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $step = ApprovalStep::where('payable_id', $payable->id)
            ->whereKey($data['step_id'])
            ->firstOrFail();

        $delegate = User::whereKey($data['delegate_user_id'])->where('is_active', true)->firstOrFail();
        $expiresAt = isset($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null;

        $workflow = app(ApprovalWorkflowService::class);
        $result = $workflow->delegateStep($step, $payable, $request->user(), $delegate, $expiresAt, $data['reason'] ?? null);

        if (! ($result['ok'] ?? false)) {
            return back()->with('error', $result['error'] ?? 'Não foi possível delegar.');
        }

        return back()->with('success', "Aprovação delegada para {$delegate->name}.");
    }

    public function revokeApprovalDelegation(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);

        if ($payable->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este título não está aguardando aprovação.');
        }

        $data = $request->validate([
            'step_id' => ['required', 'integer', 'exists:approval_steps,id'],
        ]);

        $step = ApprovalStep::where('payable_id', $payable->id)
            ->whereKey($data['step_id'])
            ->firstOrFail();

        $workflow = app(ApprovalWorkflowService::class);
        $result = $workflow->revokeStepDelegation($step, $payable, $request->user());

        if (! ($result['ok'] ?? false)) {
            return back()->with('error', $result['error'] ?? 'Não foi possível remover delegação.');
        }

        return back()->with('success', 'Delegação removida.');
    }

    /**
     * Registra o pagamento de um título APROVADO (aprovado -> pago).
     * Governado pela ALÇADA: só quem está como `pagador` paga — nem o curinga '*' fura.
     */
    public function pay(Request $request, int $id, PayableAlcadaService $alcada)
    {
        $payable = $this->findPayableForUser($id);
        $user = $request->user();

        // Elegibilidade pela alçada (segregação de função). Não checamos permissão aqui.
        if (! $alcada->isAssigned($user, 'pagador')) {
            abort(403, 'Você não está na alçada como pagador deste módulo.');
        }

        $data = $request->validate([
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(Payable::PAYMENT_METHODS))],
            'file' => ['nullable', 'file', 'max:10240'], // 10MB — comprovante opcional
        ]);

        $paid = DB::transaction(function () use ($payable, $user, $data, $request) {
            // Trava o registro para evitar pagamento concorrente (idempotência).
            $fresh = Payable::whereKey($payable->id)->lockForUpdate()->first();

            // Só de 'aprovado'. Um 2º request concorrente encontra status pós-pagamento e cai aqui.
            if ($fresh->status !== 'aprovado') {
                return false;
            }

            $old = $fresh->status;
            $fresh->update([
                'status' => 'aguardando_conciliacao',
                'paid_at' => $data['paid_at'],
                'payment_method' => $data['payment_method'] ?? null,
                'paid_by' => $user->id,
            ]);

            // Comprovante (opcional) reaproveita a estrutura de documentos do título.
            $docName = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('payables/docs', 'public');
                PayableDocument::create([
                    'payable_id' => $fresh->id,
                    'uploaded_by' => $user->id,
                    'name' => $file->getClientOriginalName(),
                    'doc_type' => 'comprovacao',
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                $docName = $file->getClientOriginalName();
            }

            $dataFmt = Carbon::parse($data['paid_at'])->format('d/m/Y');
            $forma = $data['payment_method'] ?? null;

            PayableComment::create([
                'payable_id' => $fresh->id,
                'user_id' => $user->id,
                'body' => "Pagamento registrado em {$dataFmt}"
                    . ($forma ? " · {$forma}" : '')
                    . ($docName ? " · Comprovante: {$docName}" : ''),
                'type' => 'payment',
            ]);

            AuditLogger::log(
                event: 'contas_pagar.pago',
                module: 'financeiro.contas_pagar',
                description: "Título {$fresh->title_number} pago (R$ {$fresh->amount}) em {$dataFmt}",
                auditable: $fresh,
                oldValues: ['status' => $old],
                newValues: [
                    'status' => 'aguardando_conciliacao',
                    'paid_at' => $data['paid_at'],
                    'payment_method' => $forma,
                    'paid_by' => $user->id,
                ],
            );

            return true;
        });

        if (! $paid) {
            return back()->with('error', 'Este título não está apto a ser pago.');
        }

        return back()->with('success', 'Pagamento registrado.');
    }

    /**
     * Registra a conciliação de um título PAGO (pago -> conciliado).
     * Governado pela ALÇADA: só quem está como `conciliador` concilia — nem o curinga '*' fura.
     */
    public function conciliate(Request $request, int $id, PayableAlcadaService $alcada)
    {
        $payable = $this->findPayableForUser($id);
        $user = $request->user();

        if (! $alcada->isAssigned($user, 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador deste módulo.');
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $done = DB::transaction(function () use ($payable, $user, $data) {
            $fresh = Payable::whereKey($payable->id)->lockForUpdate()->first();

            if (! in_array($fresh->status, ['pago', 'aguardando_conciliacao'], true)) {
                return false;
            }

            $old = $fresh->status;
            $fresh->update([
                'status' => 'conciliado',
                'conciliated_at' => now()->toDateString(),
                'conciliated_by' => $user->id,
                'conciliation_notes' => $data['notes'] ?? null,
            ]);

            PayableComment::create([
                'payable_id' => $fresh->id,
                'user_id' => $user->id,
                'body' => 'Conciliação realizada'
                    . (($data['notes'] ?? null) ? " — {$data['notes']}" : ''),
                'type' => 'conciliation',
            ]);

            AuditLogger::log(
                event: 'contas_pagar.conciliado',
                module: 'financeiro.contas_pagar',
                description: "Título {$fresh->title_number} conciliado (R$ {$fresh->amount})",
                auditable: $fresh,
                oldValues: ['status' => $old],
                newValues: [
                    'status' => 'conciliado',
                    'conciliated_at' => now()->toDateString(),
                    'conciliated_by' => $user->id,
                    'conciliation_notes' => $data['notes'] ?? null,
                ],
            );


 // Cria step automatico de 2a assinatura (presidencia) pos-conciliacao
 $assinante = \App\Models\PayableRole::where("role", "assinante")->first();
 if ($assinante) {
 \App\Models\ApprovalStep::create([
 "payable_id" => $fresh->id, "order" => 99, "level_name" => "presidencia_2",
 "status" => "pendente", "assigned_to" => $assinante->user_id,
 ]);
 \App\Models\Notification::create([
 "user_id" => $assinante->user_id, "title" => "2a assinatura pendente",
 "body" => "Titulo {$fresh->title_number} conciliado - aguarda encerramento",
 "type" => "approval_pending", "link" => "/financeiro/contas-pagar/{$fresh->id}",
 "data" => ["payable_id" => $fresh->id],
 ]);
 }
            return true;
        });

        if (! $done) {
            return back()->with('error', 'Este título não está apto a ser conciliado.');
        }

        return back()->with('success', 'Conciliação registrada.');
    }

    /**
     * Registra divergência de um título PAGO (pago -> divergente).
     * Governado pela ALÇADA: só quem está como `conciliador` registra divergência — nem o curinga '*' fura.
     */
    public function diverge(Request $request, int $id, PayableAlcadaService $alcada)
    {
        $payable = $this->findPayableForUser($id);
        $user = $request->user();

        if (! $alcada->isAssigned($user, 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador deste módulo.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $done = DB::transaction(function () use ($payable, $user, $data) {
            $fresh = Payable::whereKey($payable->id)->lockForUpdate()->first();

            if (! in_array($fresh->status, ['pago', 'aguardando_conciliacao'], true)) {
                return false;
            }

            $old = $fresh->status;
            $fresh->update([
                'status' => 'divergente',
                'conciliated_at' => now()->toDateString(),
                'conciliated_by' => $user->id,
                'divergence_reason' => $data['reason'],
            ]);

            PayableComment::create([
                'payable_id' => $fresh->id,
                'user_id' => $user->id,
                'body' => "Divergência registrada — {$data['reason']}",
                'type' => 'divergence',
            ]);

            AuditLogger::log(
                event: 'contas_pagar.divergente',
                module: 'financeiro.contas_pagar',
                description: "Título {$fresh->title_number} marcado como divergente: {$data['reason']}",
                auditable: $fresh,
                oldValues: ['status' => $old],
                newValues: [
                    'status' => 'divergente',
                    'conciliated_at' => now()->toDateString(),
                    'conciliated_by' => $user->id,
                    'divergence_reason' => $data['reason'],
                ],
            );

            return true;
        });

        if (! $done) {
            return back()->with('error', 'Este título não está apto para registro de divergência.');
        }

        return back()->with('success', 'Divergência registrada.');
    }

    /**
     * A4: altera o vencimento de um título. Restrito ao FINANCEIRO
     * (permissão `financeiro.contas_pagar.editar_vencimento`; curinga `*` concede).
     */
    public function updateDueDate(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);
        $user = $request->user();

        if (! $user?->hasPermission('financeiro.contas_pagar.editar_vencimento')) {
            abort(403, 'Apenas o financeiro pode alterar o vencimento.');
        }

        $data = $request->validate([
            'due_date' => ['required', 'date'],
        ]);

        $old = $payable->due_date?->toDateString();
        $novo = Carbon::parse($data['due_date'])->toDateString();
        $payable->update(['due_date' => $novo]);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $user->id,
            'body' => 'Vencimento alterado de '
                . ($old ? Carbon::parse($old)->format('d/m/Y') : '—')
                . ' para ' . Carbon::parse($novo)->format('d/m/Y'),
            'type' => 'status_change',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.vencimento_alterado',
            module: 'financeiro.contas_pagar',
            description: "Vencimento do título {$payable->title_number} alterado para " . Carbon::parse($novo)->format('d/m/Y'),
            auditable: $payable,
            oldValues: ['due_date' => $old],
            newValues: ['due_date' => $novo],
        );

        return back()->with('success', 'Vencimento atualizado.');
    }

    public function updatePaymentPriority(Request $request, int $id)
    {
        $payable = $this->findPayableForUser($id);
        $user = $request->user();

        if (! $user?->hasPermission('financeiro.contas_pagar.prioridade_gerenciar')) {
            abort(403, 'Você não tem permissão para alterar a prioridade de pagamento.');
        }

        if ($payable->status === 'encerrado') {
            return back()->with('error', 'Títulos encerrados não podem ter prioridade alterada.');
        }

        $data = $request->validate([
            'payment_priority' => ['required', Rule::in(Payable::PRIORITY_VALUES)],
            'payment_sla_date' => ['nullable', 'date'],
        ]);

        $oldLabel = $payable->payment_priority
            ? (Payable::PRIORITY_LABELS[$payable->payment_priority] ?? $payable->payment_priority)
            : '—';
        $newLabel = Payable::PRIORITY_LABELS[$data['payment_priority']];

        $payable->update([
            'payment_priority' => $data['payment_priority'],
            'payment_sla_date' => $data['payment_sla_date'] ?? $payable->payment_sla_date,
            'priority_set_by' => $user->id,
            'priority_set_at' => now(),
        ]);

        $slaText = $payable->payment_sla_date
            ? $payable->payment_sla_date->format('d/m/Y')
            : '—';

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $user->id,
            'body' => "Prioridade alterada de {$oldLabel} para {$newLabel} (SLA: {$slaText})",
            'type' => 'status_change',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.prioridade_atualizada',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number}: prioridade {$newLabel}, SLA {$slaText}",
            auditable: $payable,
        );

        return back()->with('success', 'Prioridade de pagamento atualizada.');
    }

    private function applyPaymentPriority(Payable $payable, User $user, array $data): void
    {
        $payable->update([
            'payment_priority' => $data['payment_priority'],
            'payment_sla_date' => $data['payment_sla_date'] ?? $payable->due_date?->toDateString(),
            'priority_set_by' => $user->id,
            'priority_set_at' => now(),
        ]);
    }

    public function removeDocument(int $payableId, int $docId)
    {
        $this->findPayableForUser($payableId);
        $doc = PayableDocument::where('payable_id', $payableId)->findOrFail($docId);
        Storage::disk('public')->delete($doc->path);
        $doc->delete();

        return back();
    }

    /**
     * 2ª assinatura do presidente — encerra o ciclo após conciliação (Fluxo v3.0 passo 8).
     * Só o presidente (ou substituto configurado) pode executar.
     */
    public function finalSign(Request $request, int $id, PayableAlcadaService $alcada)
    {
        $payable = $this->findPayableForUser($id);
        $user = $request->user();

        // Só quem está na alçada como 'assinante' pode dar a 2ª assinatura
        if (!$alcada->isAssigned($user, 'assinante')) {
            abort(403, 'Você não está na alçada como assinante (2ª assinatura).');
        }

        if ($payable->status !== 'conciliado') {
            return back()->with('error', 'Este título precisa estar conciliado para encerrar.');
        }

        $payable->update(['status' => 'encerrado']);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $user->id,
            'body' => '2ª assinatura — ciclo encerrado pela Presidência',
            'type' => 'approval',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.encerrado',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} encerrado (2ª assinatura: {$user->name})",
            auditable: $payable,
            oldValues: ['status' => 'conciliado'],
            newValues: ['status' => 'encerrado'],
        );

        return back()->with('success', 'Ciclo encerrado (2ª assinatura).');
    }

    /**
     * Lista usuários mencionáveis em comentários deste payable (pra autocomplete @mention).
     */
    public function mentionableUsers(Request $request, int $id)
    {
        $mentionService = app(\App\Services\MentionService::class);
        $users = $mentionService->mentionableUsers($request->user(), $id);

        return response()->json($users);
    }
}
