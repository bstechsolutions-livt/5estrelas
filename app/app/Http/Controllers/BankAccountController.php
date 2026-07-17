<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $query = BankAccount::query()
            ->with('latestStatementImport')
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            $like = "%{$s}%";
            $driver = $query->getConnection()->getDriverName();
            $query->where(function ($q) use ($like, $driver) {
                foreach (['name', 'senior_num_cco', 'senior_descricao', 'bank_code', 'bank_name', 'account_number'] as $col) {
                    if ($driver === 'pgsql') {
                        $q->orWhere($col, 'ilike', $like);
                    } else {
                        $q->orWhereRaw("LOWER({$col}) LIKE LOWER(?)", [$like]);
                    }
                }
            });
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $accounts = $query->paginate(30)->withQueryString();
        $accounts->getCollection()->transform(fn (BankAccount $a) => $a->toListArray());

        return Inertia::render('Banks/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $request->user()?->hasPermission('financeiro.bancos.gerenciar') ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Banks/Form', [
            'account' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);

        $account = BankAccount::create([
            ...$data,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect("/financeiro/bancos/{$account->id}/editar")
            ->with('success', 'Conta bancária cadastrada.');
    }

    public function edit(Request $request, BankAccount $bankAccount): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Banks/Form', [
            'account' => $bankAccount->toListArray(),
        ]);
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);

        $bankAccount->update($data);

        return redirect('/financeiro/bancos')
            ->with('success', 'Conta bancária atualizada.');
    }

    public function toggle(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorizeManage($request);

        $bankAccount->update(['is_active' => ! $bankAccount->is_active]);

        return back()->with('success', $bankAccount->is_active
            ? "Conta \"{$bankAccount->name}\" ativada."
            : "Conta \"{$bankAccount->name}\" desativada.");
    }

    private function authorizeManage(Request $request): void
    {
        if (! $request->user()?->hasPermission('financeiro.bancos.gerenciar')) {
            abort(403, 'Sem permissão para gerenciar contas bancárias.');
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'bank_code' => ['nullable', 'string', 'max:10'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'agency' => ['nullable', 'string', 'max:20'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_digit' => ['nullable', 'string', 'max:5'],
            'opening_balance' => ['nullable', 'required_with:opening_balance_date', 'numeric', 'between:-9999999999999.99,9999999999999.99'],
            'opening_balance_date' => ['nullable', 'required_with:opening_balance', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        foreach (['bank_code', 'bank_name', 'agency', 'account_number', 'account_digit'] as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $data[$key] = trim($data[$key]) ?: null;
            }
        }

        $data['name'] = trim($data['name']);
        unset($data['is_active']);

        return $data;
    }
}
