<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Comercial\Filial;
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
                    ->orWhere('apelido', 'ilike', "%{$s}%")
                    ->orWhere('cnpj', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            });
        }

        $branches = $query->paginate(20)->withQueryString();
        Branch::attachEmpresaApelido($branches->getCollection());

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
        return Inertia::render('Branches/Form', $this->formProps());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Branch::create($data);

        return redirect('/filiais')->with('success', 'Filial criada.');
    }

    public function edit(int $id)
    {
        $branch = Branch::findOrFail($id);

        return Inertia::render('Branches/Form', array_merge($this->formProps(), [
            'branch' => $branch,
        ]));
    }

    public function update(Request $request, int $id)
    {
        $branch = Branch::findOrFail($id);
        $branch->update($this->validated($request));

        return redirect('/filiais')->with('success', 'Filial atualizada.');
    }

    public function destroy(int $id)
    {
        $branch = Branch::findOrFail($id);
        $branch->delete();

        return redirect('/filiais')->with('success', 'Filial excluída.');
    }

    /** @return array<string, mixed> */
    private function formProps(): array
    {
        $seniorFiliais = Filial::query()
            ->where('ativo', true)
            ->whereNotNull('cod_emp')
            ->whereNotNull('cod_fil')
            ->orderBy('cod_emp')
            ->orderBy('cod_fil')
            ->get(['cod_emp', 'cod_fil', 'apelido', 'nome', 'fantasia'])
            ->groupBy('cod_emp')
            ->map(fn ($rows) => $rows->map(fn (Filial $f) => [
                'value' => $f->cod_fil,
                'label' => ($f->apelido ?: $f->fantasia ?: $f->nome).' (cod '.$f->cod_fil.')',
            ])->values())
            ->all();

        return [
            'empresaOptions' => Filial::empresaOptions(),
            'seniorFiliais' => $seniorFiliais,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'apelido' => ['nullable', 'string', 'max:100'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'code' => ['nullable', 'string', 'max:10'],
            'cod_emp' => ['nullable', 'integer', 'min:1'],
            'cod_fil' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);
    }
}
