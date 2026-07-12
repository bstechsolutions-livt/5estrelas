<?php

namespace App\Services;

use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\Bordero;
use App\Models\Department;
use App\Models\Payable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class FinanceiroDashboardService
{
    private const OPEN_STATUSES = ['pendente', 'em_preparacao', 'aguardando_aprovacao', 'aprovado'];

    private const PIPELINE_STATUSES = [
        'pendente',
        'em_preparacao',
        'aguardando_aprovacao',
        'aprovado',
        'pago',
    ];

    public function build(User $user): array
    {
        $departmentId = app(FinanceiroDepartmentScope::class)->resolve($user);
        $branchScope = app(PayableBranchScope::class)->resolve($user);
        $lockedDepartment = $departmentId
            ? Department::whereKey($departmentId)->first(['id', 'name'])
            : null;

        $canBorderos = $user->hasPermission('*') || $user->hasPermission('financeiro.borderos.visualizar');
        $canConciliacao = $user->hasPermission('*') || $user->hasPermission('financeiro.conciliacao.visualizar');

        return [
            'kpis' => $this->kpis($user, $departmentId, $canBorderos),
            'payables_by_status' => $this->payablesByStatus($departmentId, $user),
            'borderos_by_status' => $canBorderos ? $this->borderosByStatus($user) : null,
            'vencimentos_semanas' => $this->vencimentosPorSemana($departmentId, $user),
            'proximos_vencimentos' => $this->proximosVencimentos($departmentId, $user),
            'minhas_aprovacoes' => $this->minhasAprovacoes($user),
            'conciliacao' => $canConciliacao ? $this->conciliacaoResumo() : null,
            'department' => $lockedDepartment ? ['id' => $lockedDepartment->id, 'name' => $lockedDepartment->name] : null,
            'branches' => $branchScope['locked_branches'],
            'no_branch_access' => $branchScope['no_branch_access'],
            'permissions' => [
                'borderos' => $canBorderos,
                'conciliacao' => $canConciliacao,
            ],
        ];
    }

    private function payableQuery(?int $departmentId, User $user): Builder
    {
        $query = Payable::query();
        app(FinanceiroDepartmentScope::class)->applyFilter($query, $departmentId);
        app(PayableBranchScope::class)->applyFilter($query, $user);

        return $query;
    }

    private function kpis(User $user, ?int $departmentId, bool $canBorderos): array
    {
        $today = now()->toDateString();
        $in7 = now()->addDays(7)->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $emAbertoQuery = $this->payableQuery($departmentId, $user)
            ->where(function (Builder $q) {
                $q->where('status', 'em_preparacao')
                    ->orWhere(function (Builder $q2) {
                        $q2->where('status', 'pendente')->whereNull('bordero_id');
                    });
            });

        $aguardandoQuery = $this->payableQuery($departmentId, $user)->where('status', 'aguardando_aprovacao');
        $aprovadoQuery = $this->payableQuery($departmentId, $user)->where('status', 'aprovado');

        $vencidosQuery = $this->payableQuery($departmentId, $user)
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('due_date', '<', $today);

        $vencendo7Query = $this->payableQuery($departmentId, $user)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereBetween('due_date', [$today, $in7]);

        $pagosMesQuery = $this->payableQuery($departmentId, $user)
            ->where('status', 'pago')
            ->where('paid_at', '>=', $monthStart);

        $workflow = app(ApprovalWorkflowService::class);
        $minhasAprovacoes = $workflow->myPendingApprovals($user)->count();

        $borderosAbertos = null;
        if ($canBorderos) {
            $borderosQuery = Bordero::query()
                ->whereIn('status', ['pendente', 'em_preparacao']);
            $branchScope = app(PayableBranchScope::class);
            if ($branchScope->resolve($user)['restricted']) {
                $borderosQuery->whereHas('payables', fn ($q) => $branchScope->applyFilter($q, $user));
            }
            app(FinanceiroDepartmentScope::class)->applyBorderoFilter($borderosQuery, $user);
            $borderosAbertos = $borderosQuery
                ->selectRaw('count(*) as count, coalesce(sum(total_amount), 0) as total')
                ->first();
        }

        $urgentesQuery = $this->payableQuery($departmentId, $user)
            ->where('payment_priority', 'urgente')
            ->whereIn('status', self::OPEN_STATUSES);

        $slaEstouradoQuery = $this->payableQuery($departmentId, $user)
            ->whereNotNull('payment_sla_date')
            ->where('payment_sla_date', '<', $today)
            ->whereIn('status', self::OPEN_STATUSES);

        return [
            'em_aberto' => [
                'count' => (int) (clone $emAbertoQuery)->count(),
                'total' => (float) (clone $emAbertoQuery)->sum('amount'),
            ],
            'aguardando_aprovacao' => [
                'count' => (int) (clone $aguardandoQuery)->count(),
                'total' => (float) (clone $aguardandoQuery)->sum('amount'),
            ],
            'aprovado' => [
                'count' => (int) (clone $aprovadoQuery)->count(),
                'total' => (float) (clone $aprovadoQuery)->sum('amount'),
            ],
            'vencidos' => [
                'count' => (int) (clone $vencidosQuery)->count(),
                'total' => (float) (clone $vencidosQuery)->sum('amount'),
            ],
            'vencendo_7d' => [
                'count' => (int) (clone $vencendo7Query)->count(),
                'total' => (float) (clone $vencendo7Query)->sum('amount'),
            ],
            'pagos_mes' => [
                'count' => (int) (clone $pagosMesQuery)->count(),
                'total' => (float) (clone $pagosMesQuery)->sum('amount'),
            ],
            'minhas_aprovacoes' => $minhasAprovacoes,
            'urgentes' => [
                'count' => (int) (clone $urgentesQuery)->count(),
                'total' => (float) (clone $urgentesQuery)->sum('amount'),
            ],
            'sla_estourado' => [
                'count' => (int) (clone $slaEstouradoQuery)->count(),
                'total' => (float) (clone $slaEstouradoQuery)->sum('amount'),
            ],
            'borderos_abertos' => $borderosAbertos ? [
                'count' => (int) $borderosAbertos->count,
                'total' => (float) $borderosAbertos->total,
            ] : null,
        ];
    }

    private function payablesByStatus(?int $departmentId, User $user): array
    {
        $rows = $this->payableQuery($departmentId, $user)
            ->where(function (Builder $q) {
                $q->where('status', '!=', 'pendente')
                    ->orWhereNull('bordero_id');
            })
            ->whereIn('status', self::PIPELINE_STATUSES)
            ->selectRaw('status, count(*) as count, coalesce(sum(amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $result = [];
        foreach (self::PIPELINE_STATUSES as $status) {
            $row = $rows->get($status);
            $result[] = [
                'status' => $status,
                'label' => Payable::STATUS_LABELS[$status] ?? $status,
                'count' => (int) ($row->count ?? 0),
                'total' => (float) ($row->total ?? 0),
            ];
        }

        return $result;
    }

    private function borderosByStatus(User $user): array
    {
        $query = Bordero::query();
        $branchScope = app(PayableBranchScope::class);
        if ($branchScope->resolve($user)['restricted']) {
            $query->whereHas('payables', fn ($q) => $branchScope->applyFilter($q, $user));
        }
        $rows = $query
            ->selectRaw('status, count(*) as count, coalesce(sum(total_amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $result = [];
        foreach (array_keys(Bordero::STATUS_LABELS) as $status) {
            $row = $rows->get($status);
            $result[] = [
                'status' => $status,
                'label' => Bordero::STATUS_LABELS[$status],
                'count' => (int) ($row->count ?? 0),
                'total' => (float) ($row->total ?? 0),
            ];
        }

        return $result;
    }

    private function vencimentosPorSemana(?int $departmentId, User $user): array
    {
        $today = Carbon::today();
        $weeks = [];

        for ($w = 0; $w < 4; $w++) {
            $start = $today->copy()->addDays($w * 7);
            $end = $today->copy()->addDays(($w + 1) * 7 - 1);

            $query = $this->payableQuery($departmentId, $user)
                ->whereIn('status', self::OPEN_STATUSES)
                ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()]);

            $weeks[] = [
                'label' => $w === 0 ? 'Esta semana' : "Sem. +{$w}",
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'count' => (int) (clone $query)->count(),
                'total' => (float) (clone $query)->sum('amount'),
            ];
        }

        return $weeks;
    }

    private function proximosVencimentos(?int $departmentId, User $user): array
    {
        $today = now()->toDateString();

        $items = $this->payableQuery($departmentId, $user)
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('due_date', '>=', $today)
            ->orderBy('due_date')
            ->limit(8)
            ->get(['id', 'title_number', 'supplier_name', 'amount', 'due_date', 'status']);

        return $items->map(fn (Payable $p) => [
            'id' => $p->id,
            'title_number' => $p->title_number,
            'supplier_name' => $p->supplier_name,
            'amount' => (float) $p->amount,
            'due_date' => $p->due_date?->toDateString(),
            'status' => $p->status,
            'status_label' => Payable::STATUS_LABELS[$p->status] ?? $p->status,
        ])->all();
    }

    private function minhasAprovacoes(User $user): array
    {
        $workflow = app(ApprovalWorkflowService::class);
        $payables = $workflow->myPendingApprovals($user);

        return [
            'count' => $payables->count(),
            'items' => $payables->take(5)->map(fn (Payable $p) => [
                'id' => $p->id,
                'title_number' => $p->title_number,
                'supplier_name' => $p->supplier_name,
                'amount' => (float) $p->amount,
                'due_date' => $p->due_date?->toDateString(),
            ])->all(),
        ];
    }

    private function conciliacaoResumo(): array
    {
        $imports = BankStatementImport::query()->count();
        $pending = BankTransaction::query()->where('match_status', 'pending')->count();
        $unmatched = BankTransaction::query()->where('match_status', 'unmatched')->count();
        $accepted = BankTransaction::query()->whereIn('match_status', ['accepted', 'manual'])->count();

        return [
            'imports' => $imports,
            'pending' => $pending,
            'unmatched' => $unmatched,
            'matched' => $accepted,
        ];
    }
}
