<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = ChartOfAccount::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('code', 'ilike', "%{$s}%")
                    ->orWhere('description', 'ilike', "%{$s}%");
            });
        }
        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }
        if ($request->filled('codemp')) {
            $query->where('codemp', (int) $request->codemp);
        }

        $accounts = $query->orderBy('account_type')->orderBy('code')->paginate(50)->withQueryString();

        ChartOfAccount::attachEmpresaNome($accounts->getCollection());
        ChartOfAccount::attachDerivedDescriptions($accounts->getCollection());

        return Inertia::render('ChartOfAccounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['search', 'account_type', 'codemp']),
            'typeOptions' => ChartOfAccount::TYPE_LABELS,
            'isInterimSource' => true,
        ]);
    }
}
