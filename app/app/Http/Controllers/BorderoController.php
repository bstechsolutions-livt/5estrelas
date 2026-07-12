<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\Bordero;
use App\Models\Payable;
use App\Services\ApprovalWorkflowService;
use App\Services\AuditLogger;
use App\Services\BorderoActionService;
use App\Services\FinanceiroDepartmentScope;
use App\Services\PayableBranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BorderoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branchScope = app(PayableBranchScope::class);
        $departmentScope = app(FinanceiroDepartmentScope::class);
        $scope = $branchScope->resolve($user);

        $query = Bordero::query()
            ->with('creator:id,name')
            ->orderByDesc('created_at');

        $status = $request->input('status') ?: 'aguardando_aprovacao';
        $query->where('status', $status);

        if ($scope['restricted']) {
            $query->whereHas('payables', fn ($q) => $branchScope->applyFilter($q, $user));
        }
        $departmentScope->applyBorderoFilter($query, $user);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('number', 'ilike', "%{$s}%")
                    ->orWhere('description', 'ilike', "%{$s}%");
            });
        }

        $borderos = $query->paginate($request->input('per_page', 20))->withQueryString();

        $totalsQuery = Bordero::query();
        if ($scope['restricted']) {
            $totalsQuery->whereHas('payables', fn ($q) => $branchScope->applyFilter($q, $user));
        }
        $departmentScope->applyBorderoFilter($totalsQuery, $user);
        $totals = $totalsQuery
            ->selectRaw("status, count(*) as count, coalesce(sum(total_amount), 0) as total")
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('Borderos/Index', [
            'borderos' => $borderos,
            'totals' => $totals,
            'filters' => array_merge(
                $request->only(['search']),
                ['status' => $status],
            ),
            'statusOptions' => Bordero::STATUS_LABELS,
            'noBranchAccess' => $scope['no_branch_access'],
            'canManageAuto' => $user->hasPermission('financeiro.borderos.automatico_gerenciar'),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $bordero = Bordero::with([
            'creator:id,name',
            'approver:id,name',
            'payables' => fn ($q) => $q->with('branch:id,name')->withCount('documents')->orderBy('due_date'),
        ])->findOrFail($id);

        $user = $request->user();
        $branchScope = app(PayableBranchScope::class);
        if (! $bordero->payables->contains(fn (Payable $p) => $branchScope->canAccessPayable($user, $p))) {
            $scope = $branchScope->resolve($user);
            abort(403, $scope['no_branch_access']
                ? PayableBranchScope::NO_BRANCH_ACCESS_MESSAGE
                : 'Você não tem acesso a este borderô.');
        }

        Payable::attachEmpresaNome($bordero->payables);
        Payable::attachFilialNome($bordero->payables);

        $workflow = app(ApprovalWorkflowService::class);
        $actions = app(BorderoActionService::class);

        $payableIds = $bordero->payables->pluck('id');
        $stepsByPayable = ApprovalStep::whereIn('payable_id', $payableIds)
            ->with(['assignee:id,name', 'resolver:id,name'])
            ->orderBy('order')
            ->get()
            ->groupBy('payable_id');

        $payablesWithWorkflow = $bordero->payables->map(function (Payable $payable) use ($workflow, $stepsByPayable, $user, $actions) {
            $steps = $stepsByPayable->get($payable->id, collect());
            $currentStep = $workflow->currentStep($payable);

            return [
                'payable' => $payable,
                'approval_steps' => $steps,
                'current_step' => $currentStep,
                'can_approve' => $workflow->canUserApprove($payable, $user),
                'can_expulsar' => $actions->canExpulsarTitulo($user, $payable),
            ];
        });

        $canApproveStep = $payablesWithWorkflow->contains(fn ($row) => $row['can_approve']);
        $approvableCount = $payablesWithWorkflow->where('can_approve', true)->count();
        $currentStepLabel = $payablesWithWorkflow
            ->first(fn ($row) => $row['can_approve'])['current_step']?->level_name ?? null;

        return Inertia::render('Borderos/Show', [
            'bordero' => $bordero,
            'statusLabels' => Bordero::STATUS_LABELS,
            'statusColors' => Bordero::STATUS_COLORS,
            'payablesWorkflow' => $payablesWithWorkflow,
            'canApproveStep' => $canApproveStep,
            'canReprovarBordero' => $actions->canReprovarBordero($bordero, $user),
            'canLiberarTitulo' => $actions->canLiberarTitulo($user),
            'canDesfazer' => $actions->canDesfazer($user) && $bordero->isEditable(),
            'approvableCount' => $approvableCount,
            'currentStepLabel' => $currentStepLabel,
            'requiresPriorityOnApprove' => $canApproveStep && $currentStepLabel === 'financeiro',
            'priorityOptions' => Payable::PRIORITY_LABELS,
            'approvalPreview' => $workflow->buildPreviewStepsForSender($user),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payable_ids' => ['required', 'array', 'min:1'],
            'payable_ids.*' => ['integer', 'exists:payables,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $branchScope = app(PayableBranchScope::class);

        $payablesQuery = Payable::whereIn('id', $data['payable_ids'])
            ->excludeMissingInSenior()
            ->whereIn('status', ['pendente', 'em_preparacao'])
            ->whereNull('bordero_id');
        $branchScope->applyFilter($payablesQuery, $user);
        $payables = $payablesQuery->get();

        if ($payables->isEmpty()) {
            return back()->with('error', 'Nenhum título válido para agrupar (devem estar pendentes e fora de outro borderô).');
        }

        $bordero = DB::transaction(function () use ($payables, $data, $request) {
            $bordero = Bordero::create([
                'number' => Bordero::generateNumber(),
                'description' => $data['description'] ?? null,
                'status' => Bordero::STATUS_PENDENTE,
                'created_by' => $request->user()->id,
            ]);

            Payable::whereIn('id', $payables->pluck('id'))->update([
                'bordero_id' => $bordero->id,
                'prepared_by' => $request->user()->id,
            ]);

            $bordero->recalculate();

            return $bordero;
        });

        AuditLogger::log(
            event: 'bordero.created',
            module: 'financeiro.contas_pagar',
            description: "Borderô {$bordero->number} criado com {$bordero->items_count} título(s) - R$ {$bordero->total_amount}",
            auditable: $bordero,
        );

        return redirect("/financeiro/borderos/{$bordero->id}")->with('success', 'Borderô criado.');
    }

    public function removePayable(int $borderoId, int $payableId)
    {
        $bordero = Bordero::findOrFail($borderoId);

        if (! $bordero->isEditable()) {
            return back()->with('error', 'Só é possível alterar borderôs pendentes ou em preparação.');
        }

        Payable::where('id', $payableId)->where('bordero_id', $borderoId)
            ->update(['bordero_id' => null]);

        $bordero->recalculate();

        if ($bordero->items_count === 0) {
            $bordero->delete();

            return redirect('/financeiro/borderos')->with('success', 'Borderô removido (ficou vazio).');
        }

        $bordero->syncStatusFromPayables();

        return back()->with('success', 'Título removido do borderô.');
    }

    public function sendForApproval(Request $request, int $id)
    {
        $bordero = Bordero::with('payables')->findOrFail($id);

        if (! in_array($bordero->status, [Bordero::STATUS_PENDENTE, Bordero::STATUS_EM_PREPARACAO], true)) {
            return back()->with('error', 'Este borderô não pode ser enviado.');
        }

        if ($bordero->payables->isEmpty()) {
            return back()->with('error', 'O borderô não possui títulos.');
        }

        $workflow = app(ApprovalWorkflowService::class);
        $preview = $workflow->buildPreviewStepsForSender($request->user());
        if (! $preview['ok']) {
            return back()->with('error', $preview['errors'][0] ?? 'Não foi possível enviar para aprovação.');
        }

        $withoutDocs = $bordero->payables->filter(fn (Payable $p) => $p->documents()->count() === 0);
        if ($withoutDocs->isNotEmpty()) {
            $nums = $withoutDocs->pluck('title_number')->take(3)->join(', ');

            return back()->with('error', "Anexe ao menos um documento em cada título antes de enviar. Sem anexo: {$nums}.");
        }

        try {
            DB::transaction(function () use ($bordero, $request, $workflow) {
                foreach ($bordero->payables as $payable) {
                    $workflow->sendForApproval($payable, $request->user());
                }

                $bordero->update([
                    'status' => Bordero::STATUS_AGUARDANDO_APROVACAO,
                    'sent_for_approval_at' => now(),
                    'rejection_reason' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log(
            event: 'bordero.enviado_aprovacao',
            module: 'financeiro.contas_pagar',
            description: "Borderô {$bordero->number} enviado para aprovação multinível ({$bordero->items_count} títulos, R$ {$bordero->total_amount})",
            auditable: $bordero,
        );

        return back()->with('success', 'Borderô enviado para o fluxo de aprovação.');
    }

    public function approve(Request $request, int $id)
    {
        $bordero = Bordero::with('payables')->findOrFail($id);

        if ($bordero->status !== Bordero::STATUS_AGUARDANDO_APROVACAO) {
            return back()->with('error', 'Este borderô não está aguardando aprovação.');
        }

        $workflow = app(ApprovalWorkflowService::class);
        $user = $request->user();

        $samplePayable = $bordero->payables->first(
            fn (Payable $p) => $workflow->canUserApprove($p, $user)
        );
        if ($samplePayable && $workflow->isFinanceStep($workflow->currentStep($samplePayable))) {
            $priorityData = $request->validate([
                'payment_priority' => ['required', Rule::in(Payable::PRIORITY_VALUES)],
                'payment_sla_date' => ['nullable', 'date'],
            ]);

            foreach ($bordero->payables as $payable) {
                if (! $workflow->canUserApprove($payable, $user)) {
                    continue;
                }
                $payable->update([
                    'payment_priority' => $priorityData['payment_priority'],
                    'payment_sla_date' => $priorityData['payment_sla_date'] ?? $payable->due_date?->toDateString(),
                    'priority_set_by' => $user->id,
                    'priority_set_at' => now(),
                ]);
            }
        }

        $result = $workflow->approveEligibleInBordero(
            $bordero->payables,
            $user,
            $request->input('comment'),
        );

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        $bordero->refresh();
        $bordero->load('payables');
        $bordero->syncStatusFromPayables();

        if ($bordero->status === Bordero::STATUS_APROVADO) {
            $bordero->update(['approved_by' => $request->user()->id]);
        }

        AuditLogger::log(
            event: 'bordero.etapa_aprovada',
            module: 'financeiro.contas_pagar',
            description: "Borderô {$bordero->number}: {$result['count']} título(s) aprovado(s) na etapa atual",
            auditable: $bordero,
        );

        return back()->with('success', "{$result['count']} título(s) aprovado(s). {$result['message']}");
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $bordero = Bordero::with('payables')->findOrFail($id);
        $actions = app(BorderoActionService::class);

        try {
            $actions->reprovarBordero($bordero, $request->user(), $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Borderô reprovado e devolvido para pendente.');
    }

    public function liberarTitulo(Request $request, int $borderoId, int $payableId)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $bordero = Bordero::findOrFail($borderoId);
        $payable = Payable::where('id', $payableId)->where('bordero_id', $borderoId)->firstOrFail();
        $actions = app(BorderoActionService::class);

        try {
            $actions->liberarTitulo($bordero, $payable, $request->user(), $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($bordero->fresh()?->items_count === 0) {
            return redirect('/financeiro/borderos')->with('success', 'Título liberado. Borderô encerrado (sem títulos restantes).');
        }

        return back()->with('success', 'Título liberado do borderô e seguirá o fluxo avulso.');
    }

    public function expulsarTitulo(Request $request, int $borderoId, int $payableId)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $bordero = Bordero::findOrFail($borderoId);
        $payable = Payable::where('id', $payableId)->where('bordero_id', $borderoId)->firstOrFail();
        $actions = app(BorderoActionService::class);

        try {
            $actions->expulsarTitulo($bordero, $payable, $request->user(), $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! Bordero::find($borderoId)) {
            return redirect('/financeiro/borderos')->with('success', 'Título expulso. Borderô encerrado (sem títulos restantes).');
        }

        return back()->with('success', 'Título expulso do borderô e devolvido para CP pendente avulso.');
    }

    public function desfazer(Request $request, int $id)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $bordero = Bordero::with('payables')->findOrFail($id);
        $actions = app(BorderoActionService::class);

        try {
            $actions->desfazer($bordero, $request->user(), $data['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect('/financeiro/borderos?status=pendente')->with('success', 'Borderô desfeito. Títulos devolvidos para CP pendente avulso.');
    }
}
