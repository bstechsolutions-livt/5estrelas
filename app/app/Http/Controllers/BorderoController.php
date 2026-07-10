<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\Bordero;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Services\ApprovalWorkflowService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BorderoController extends Controller
{
    public function index(Request $request)
    {
        $query = Bordero::query()
            ->with('creator:id,name')
            ->orderByDesc('created_at');

        // Sempre filtra por um status (default: aguardando_aprovacao). Não existe "ver todos".
        $status = $request->input('status') ?: 'aguardando_aprovacao';
        $query->where('status', $status);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('number', 'ilike', "%{$s}%")
                    ->orWhere('description', 'ilike', "%{$s}%");
            });
        }

        $borderos = $query->paginate($request->input('per_page', 20))->withQueryString();

        $totals = Bordero::query()
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
        $workflow = app(ApprovalWorkflowService::class);

        $payableIds = $bordero->payables->pluck('id');
        $stepsByPayable = ApprovalStep::whereIn('payable_id', $payableIds)
            ->with(['assignee:id,name', 'resolver:id,name'])
            ->orderBy('order')
            ->get()
            ->groupBy('payable_id');

        $payablesWithWorkflow = $bordero->payables->map(function (Payable $payable) use ($workflow, $stepsByPayable, $user) {
            $steps = $stepsByPayable->get($payable->id, collect());
            $currentStep = $workflow->currentStep($payable);

            return [
                'payable' => $payable,
                'approval_steps' => $steps,
                'current_step' => $currentStep,
                'can_approve' => $workflow->canUserApprove($payable, $user),
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
            'approvableCount' => $approvableCount,
            'currentStepLabel' => $currentStepLabel,
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

        // Valida que os títulos estão em status que permite agrupar
        $payables = Payable::whereIn('id', $data['payable_ids'])
            ->whereIn('status', ['pendente', 'em_preparacao', 'reprovado'])
            ->whereNull('bordero_id')
            ->get();

        if ($payables->isEmpty()) {
            return back()->with('error', 'Nenhum título válido para agrupar (devem estar pendentes e fora de outro borderô).');
        }

        $bordero = DB::transaction(function () use ($payables, $data, $request) {
            $bordero = Bordero::create([
                'number' => Bordero::generateNumber(),
                'description' => $data['description'] ?? null,
                'status' => 'rascunho',
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

        if ($bordero->status !== 'rascunho') {
            return back()->with('error', 'Só é possível alterar borderôs em rascunho.');
        }

        Payable::where('id', $payableId)->where('bordero_id', $borderoId)
            ->update(['bordero_id' => null]);

        $bordero->recalculate();

        if ($bordero->items_count === 0) {
            $bordero->delete();
            return redirect('/financeiro/borderos')->with('success', 'Borderô removido (ficou vazio).');
        }

        return back()->with('success', 'Título removido do borderô.');
    }

    public function sendForApproval(Request $request, int $id)
    {
        $bordero = Bordero::with('payables')->findOrFail($id);

        if ($bordero->status !== 'rascunho') {
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
                    'status' => 'aguardando_aprovacao',
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

        if ($bordero->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este borderô não está aguardando aprovação.');
        }

        $workflow = app(ApprovalWorkflowService::class);
        $result = $workflow->approveEligibleInBordero(
            $bordero->payables,
            $request->user(),
            $request->input('comment')
        );

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        $bordero->refresh();
        $bordero->load('payables');
        $bordero->syncStatusFromPayables();

        if ($bordero->status === 'aprovado') {
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
        $bordero = Bordero::with('payables')->findOrFail($id);

        if ($bordero->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este borderô não está aguardando aprovação.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $workflow = app(ApprovalWorkflowService::class);
        $result = $workflow->rejectEligibleInBordero(
            $bordero->payables,
            $request->user(),
            $data['reason']
        );

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        $bordero->refresh();
        $bordero->load('payables');
        $bordero->syncStatusFromPayables();

        AuditLogger::log(
            event: 'bordero.reprovado',
            module: 'financeiro.contas_pagar',
            description: "Borderô {$bordero->number}: {$result['count']} título(s) reprovado(s): {$data['reason']}",
            auditable: $bordero,
        );

        return back()->with('success', "{$result['count']} título(s) reprovado(s).");
    }
}
