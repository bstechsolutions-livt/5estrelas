<?php

namespace App\Http\Controllers;

use App\Models\Receivable;
use App\Services\ReceivableBranchScope;
use App\Support\FilterDate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReceivableController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branchScope = app(ReceivableBranchScope::class)->resolve($user);

        if ($branchScope['no_branch_access']) {
            return Inertia::render('Receivables/Index', [
                'receivables' => ['data' => [], 'links' => [], 'meta' => []],
                'totals' => [],
                'filters' => $request->only(['search', 'due_from', 'due_to', 'codemp', 'status']),
                'empresas' => [],
                'statusOptions' => Receivable::SITUACAO_LABELS,
                'lockedBranches' => [],
                'noBranchAccess' => true,
            ]);
        }

        $query = Receivable::query()
            ->excludeMissingInSenior()
            ->with(['branch:id,name']);

        $status = $request->input('status');
        if ($status) {
            $query->where('senior_situacao_original', strtoupper($status));
        } else {
            $query->where(function ($q) {
                $q->whereNull('senior_situacao_original')
                    ->orWhereIn('senior_situacao_original', ['AB', 'ABE', 'NOR', 'PEN']);
            });
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('customer_name', 'ilike', "%{$s}%")
                    ->orWhere('title_number', 'ilike', "%{$s}%")
                    ->orWhere('description', 'ilike', "%{$s}%");
            });
        }
        $dueFrom = FilterDate::parse($request->input('due_from'));
        $dueTo = FilterDate::parse($request->input('due_to'));
        if ($dueFrom) {
            $query->where('due_date', '>=', $dueFrom);
        }
        if ($dueTo) {
            $query->where('due_date', '<=', $dueTo);
        }
        if ($request->filled('codemp')) {
            $query->where('codemp', (int) $request->codemp);
        }

        app(ReceivableBranchScope::class)->applyFilter($query, $user);

        $query->orderBy('due_date')->orderBy('id');

        $receivables = $query->paginate(20)->withQueryString();
        Receivable::attachEmpresaNome($receivables->getCollection());
        Receivable::attachFilialNome($receivables->getCollection());
        Receivable::attachOrigemSenior($receivables->getCollection());

        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($receivables);
        }

        $totalsQuery = Receivable::query()->excludeMissingInSenior();
        app(ReceivableBranchScope::class)->applyFilter($totalsQuery, $user);
        $totals = $totalsQuery
            ->selectRaw('senior_situacao_original as status, count(*) as count, coalesce(sum(amount), 0) as total')
            ->groupBy('senior_situacao_original')
            ->get()
            ->keyBy('status');

        return Inertia::render('Receivables/Index', [
            'receivables' => $receivables,
            'totals' => $totals,
            'filters' => $request->only(['search', 'due_from', 'due_to', 'codemp', 'status']),
            'empresas' => app(ReceivableBranchScope::class)->empresaOptionsForUser($user),
            'statusOptions' => Receivable::SITUACAO_LABELS,
            'lockedBranches' => $branchScope['locked_branches'],
            'noBranchAccess' => false,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $receivable = Receivable::with(['branch:id,name', 'rateios'])->findOrFail($id);
        $user = $request->user();

        if (!app(ReceivableBranchScope::class)->canAccessReceivable($user, $receivable)) {
            abort(403, ReceivableBranchScope::NO_BRANCH_ACCESS_MESSAGE);
        }

        Receivable::attachEmpresaNome([$receivable]);
        Receivable::attachFilialNome([$receivable]);
        Receivable::attachOrigemSenior([$receivable]);

        return Inertia::render('Receivables/Show', [
            'receivable' => $receivable,
            'statusLabels' => Receivable::SITUACAO_LABELS,
        ]);
    }
}
