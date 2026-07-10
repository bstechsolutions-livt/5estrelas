<?php

namespace App\Http\Controllers;

use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::query()
            ->with([
                'manager:id,name',
                'director:id,name',
                'users' => fn ($q) => $q->where('is_active', true)->orderBy('name')->select('id', 'name', 'department_id'),
            ])
            ->withCount(['users' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name');

        if ($request->boolean('inactive')) {
            $query->where('is_active', false);
        } else {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%");
        }

        $departments = $query->paginate(20)->withQueryString();

        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($departments);
        }

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
            'filters' => [
                'search' => $request->search,
                'inactive' => $request->boolean('inactive'),
            ],
            'approvalAreas' => ApprovalTrail::AREAS,
        ]);
    }

    public function create()
    {
        return Inertia::render('Departments/Form', $this->formProps());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Department::create($data);

        return redirect('/departamentos')->with('success', 'Departamento criado.');
    }

    public function edit(int $id)
    {
        $department = Department::with(['manager:id,name', 'director:id,name'])->findOrFail($id);

        return Inertia::render('Departments/Form', array_merge($this->formProps(), [
            'department' => $department,
        ]));
    }

    public function update(Request $request, int $id)
    {
        $department = Department::findOrFail($id);
        $department->update($this->validated($request, $id));

        return redirect('/departamentos')->with('success', 'Departamento atualizado.');
    }

    public function destroy(int $id)
    {
        $department = Department::findOrFail($id);
        $department->delete();

        return redirect('/departamentos')->with('success', 'Departamento excluído.');
    }

    /** @return array<string, mixed> */
    private function formProps(): array
    {
        return [
            'approvalAreas' => collect(ApprovalTrail::AREAS)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('departments', 'name')->ignore($id)],
            'is_active' => ['boolean'],
            'area_key' => ['nullable', 'string', Rule::in(array_keys(ApprovalTrail::AREAS))],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'director_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }
}
