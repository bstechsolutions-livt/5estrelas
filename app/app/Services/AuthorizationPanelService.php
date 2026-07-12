<?php

namespace App\Services;

use App\Models\Bordero;
use App\Models\Department;
use App\Models\Payable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AuthorizationPanelService
{
    public function build(User $user): array
    {
        $departmentId = app(FinanceiroDepartmentScope::class)->resolve($user);
        $canBorderos = $user->hasPermission('*') || $user->hasPermission('financeiro.borderos.visualizar');
        $workflow = app(ApprovalWorkflowService::class);

        $summary = $this->summaryCounts($user, $departmentId, $canBorderos);
        $myAction = $this->itemsAwaitingMyAction($user, $workflow, $canBorderos);
        $inApproval = $this->itemsInApproval($user, $departmentId, $canBorderos, 30);

        return [
            'summary' => $summary,
            'my_action' => $myAction,
            'in_approval' => $inApproval,
            'department' => $departmentId
                ? Department::whereKey($departmentId)->first(['id', 'name'])?->only(['id', 'name'])
                : null,
            'permissions' => ['borderos' => $canBorderos],
            'no_branch_access' => app(PayableBranchScope::class)->resolve($user)['no_branch_access'],
        ];
    }

    private function payableQuery(?int $departmentId, User $user): Builder
    {
        $query = Payable::query();
        app(FinanceiroDepartmentScope::class)->applyFilter($query, $departmentId);
        app(PayableBranchScope::class)->applyFilter($query, $user);

        return $query;
    }

    private function borderoQuery(User $user): Builder
    {
        $query = Bordero::query();
        $branchScope = app(PayableBranchScope::class);
        if ($branchScope->resolve($user)['restricted']) {
            $query->whereHas('payables', fn ($q) => $branchScope->applyFilter($q, $user));
        }
        app(FinanceiroDepartmentScope::class)->applyBorderoFilter($query, $user);

        return $query;
    }

    private function summaryCounts(User $user, ?int $departmentId, bool $canBorderos): array
    {
        $aguardandoPayables = (clone $this->payableQuery($departmentId, $user))
            ->where('status', 'aguardando_aprovacao');

        $aprovadoPayables = (clone $this->payableQuery($departmentId, $user))
            ->where('status', 'aprovado');

        $recusadoPayables = (clone $this->payableQuery($departmentId, $user))
            ->where('status', 'pendente')
            ->whereNotNull('rejection_reason')
            ->where(function (Builder $q) {
                $q->whereNull('bordero_id')
                    ->orWhereHas('bordero', fn (Builder $b) => $b->where('status', '!=', 'aguardando_aprovacao'));
            });

        $aguardandoBorderos = null;
        $aprovadoBorderos = null;
        $recusadoBorderos = null;

        if ($canBorderos) {
            $aguardandoBorderos = (clone $this->borderoQuery($user))->where('status', 'aguardando_aprovacao');
            $aprovadoBorderos = (clone $this->borderoQuery($user))->where('status', 'aprovado');
            $recusadoBorderos = (clone $this->borderoQuery($user))
                ->where('status', 'pendente')
                ->whereNotNull('rejection_reason');
        }

        return [
            'aguardando_aprovacao' => [
                'count' => (int) $aguardandoPayables->count() + (int) ($aguardandoBorderos?->count() ?? 0),
                'payables' => (int) $aguardandoPayables->count(),
                'borderos' => (int) ($aguardandoBorderos?->count() ?? 0),
            ],
            'aprovado' => [
                'count' => (int) $aprovadoPayables->count() + (int) ($aprovadoBorderos?->count() ?? 0),
                'payables' => (int) $aprovadoPayables->count(),
                'borderos' => (int) ($aprovadoBorderos?->count() ?? 0),
            ],
            'recusado' => [
                'count' => (int) $recusadoPayables->count() + (int) ($recusadoBorderos?->count() ?? 0),
                'payables' => (int) $recusadoPayables->count(),
                'borderos' => (int) ($recusadoBorderos?->count() ?? 0),
            ],
        ];
    }

    private function itemsAwaitingMyAction(User $user, ApprovalWorkflowService $workflow, bool $canBorderos): array
    {
        $payables = $workflow->myPendingApprovals($user)->map(fn (Payable $p) => [
            'type' => 'payable',
            'id' => $p->id,
            'label' => $p->supplier_name ?: $p->title_number,
            'subtitle' => $p->title_number,
            'amount' => (float) $p->amount,
            'due_date' => $p->due_date?->toDateString(),
            'status' => $p->status,
            'status_label' => Payable::STATUS_LABELS[$p->status] ?? $p->status,
            'preparer' => $p->preparer?->name,
            'href' => "/financeiro/contas-pagar/{$p->id}",
        ])->values()->all();

        $borderos = [];
        if ($canBorderos) {
            $borderos = $this->myPendingBorderos($user, $workflow);
        }

        return array_merge($payables, $borderos);
    }

    private function myPendingBorderos(User $user, ApprovalWorkflowService $workflow): array
    {
        $candidates = $this->borderoQuery($user)
            ->where('status', 'aguardando_aprovacao')
            ->with(['payables' => fn ($q) => $q->select('id', 'bordero_id', 'status', 'amount')])
            ->orderByDesc('sent_for_approval_at')
            ->get();

        $items = [];
        foreach ($candidates as $bordero) {
            $approvable = $bordero->payables->first(
                fn (Payable $p) => $p->status === 'aguardando_aprovacao' && $workflow->canUserApprove($p, $user)
            );
            if (! $approvable) {
                continue;
            }

            $items[] = [
                'type' => 'bordero',
                'id' => $bordero->id,
                'label' => $bordero->number,
                'subtitle' => $bordero->description ?: "{$bordero->items_count} título(s)",
                'amount' => (float) $bordero->total_amount,
                'due_date' => null,
                'status' => $bordero->status,
                'status_label' => Bordero::STATUS_LABELS[$bordero->status] ?? $bordero->status,
                'preparer' => null,
                'href' => "/financeiro/borderos/{$bordero->id}",
            ];
        }

        return $items;
    }

    private function itemsInApproval(User $user, ?int $departmentId, bool $canBorderos, int $limit): array
    {
        $payables = $this->payableQuery($departmentId, $user)
            ->where('status', 'aguardando_aprovacao')
            ->with(['preparer:id,name'])
            ->orderByDesc('sent_for_approval_at')
            ->limit($limit)
            ->get(['id', 'title_number', 'supplier_name', 'amount', 'due_date', 'status', 'prepared_by', 'sent_for_approval_at']);

        $items = $payables->map(fn (Payable $p) => [
            'type' => 'payable',
            'id' => $p->id,
            'label' => $p->supplier_name ?: $p->title_number,
            'subtitle' => $p->title_number,
            'amount' => (float) $p->amount,
            'due_date' => $p->due_date?->toDateString(),
            'status' => $p->status,
            'status_label' => Payable::STATUS_LABELS[$p->status] ?? $p->status,
            'sent_at' => $p->sent_for_approval_at?->toDateString(),
            'preparer' => $p->preparer?->name,
            'href' => "/financeiro/contas-pagar/{$p->id}",
        ])->all();

        if (! $canBorderos) {
            return $items;
        }

        $borderos = $this->borderoQuery($user)
            ->where('status', 'aguardando_aprovacao')
            ->with('creator:id,name')
            ->orderByDesc('sent_for_approval_at')
            ->limit($limit)
            ->get(['id', 'number', 'description', 'total_amount', 'items_count', 'status', 'created_by', 'sent_for_approval_at']);

        foreach ($borderos as $bordero) {
            $items[] = [
                'type' => 'bordero',
                'id' => $bordero->id,
                'label' => $bordero->number,
                'subtitle' => $bordero->description ?: "{$bordero->items_count} título(s)",
                'amount' => (float) $bordero->total_amount,
                'due_date' => null,
                'status' => $bordero->status,
                'status_label' => Bordero::STATUS_LABELS[$bordero->status] ?? $bordero->status,
                'sent_at' => $bordero->sent_for_approval_at?->toDateString(),
                'preparer' => $bordero->creator?->name,
                'href' => "/financeiro/borderos/{$bordero->id}",
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['sent_at'] ?? '', $a['sent_at'] ?? ''));

        return array_slice($items, 0, $limit);
    }
}
