<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::query()->withCount('users')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%");
        }

        $departments = $query->paginate(20)->withQueryString();

        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($departments);
        }

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
            'filters' => ['search' => $request->search],
        ]);
    }

    public function create()
    {
        return Inertia::render('Departments/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:departments,name'],
            'is_active' => ['boolean'],
        ]);

        Department::create($data);

        return redirect('/departamentos')->with('success', 'Departamento criado.');
    }

    public function edit(int $id)
    {
        $department = Department::findOrFail($id);

        return Inertia::render('Departments/Form', [
            'department' => $department,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $department = Department::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', "unique:departments,name,{$id}"],
            'is_active' => ['boolean'],
        ]);

        $department->update($data);

        return redirect('/departamentos')->with('success', 'Departamento atualizado.');
    }

    public function destroy(int $id)
    {
        $department = Department::findOrFail($id);
        $department->delete();

        return redirect('/departamentos')->with('success', 'Departamento excluído.');
    }
}
