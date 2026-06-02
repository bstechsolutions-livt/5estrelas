<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::query()->withCount('users')->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")
                    ->orWhere('cnpj', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            });
        }

        $branches = $query->paginate(20)->withQueryString();

        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($branches);
        }

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'filters' => ['search' => $request->search],
        ]);
    }

    public function create()
    {
        return Inertia::render('Branches/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'code' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ]);

        Branch::create($data);

        return redirect('/filiais')->with('success', 'Filial criada.');
    }

    public function edit(int $id)
    {
        $branch = Branch::findOrFail($id);

        return Inertia::render('Branches/Form', [
            'branch' => $branch,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $branch = Branch::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'code' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ]);

        $branch->update($data);

        return redirect('/filiais')->with('success', 'Filial atualizada.');
    }

    public function destroy(int $id)
    {
        $branch = Branch::findOrFail($id);
        $branch->delete();

        return redirect('/filiais')->with('success', 'Filial excluída.');
    }
}
