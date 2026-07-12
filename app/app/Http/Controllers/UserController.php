<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);

        $query = User::query()
            ->with(['department:id,name', 'branches:id,name,code'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $users = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        // Endpoint JSON para infinite scroll mobile
        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($users);
        }

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'totalBranches' => Branch::where('is_active', true)->count(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Form', [
            'mode' => 'create',
            'user' => null,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', new \App\Rules\StrongPassword()],
            'is_active' => ['boolean'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'senior_cod_usu' => ['nullable', 'integer', 'min:1'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'is_active' => $data['is_active'] ?? true,
            'department_id' => $data['department_id'] ?? null,
            'senior_cod_usu' => $data['senior_cod_usu'] ?? null,
        ]);

        $newBranchIds = $this->normalizeBranchIds($data['branch_ids'] ?? []);
        $user->branches()->sync($newBranchIds);

        if ($newBranchIds !== []) {
            AuditLogger::log(
                event: 'usuarios.filiais_atualizadas',
                module: 'usuarios',
                description: "Filiais de {$user->name} definidas",
                auditable: $user,
                oldValues: ['branch_ids' => []],
                newValues: ['branch_ids' => $newBranchIds],
            );
        }

        return redirect('/usuarios')->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(int $id)
    {
        $user = User::with('branches:id')->findOrFail($id);

        return Inertia::render('Users/Form', [
            'mode' => 'edit',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'department_id' => $user->department_id,
                'senior_cod_usu' => $user->senior_cod_usu,
                'branch_ids' => $user->branches->pluck('id')->all(),
            ],
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', new \App\Rules\StrongPassword()],
            'is_active' => ['boolean'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'senior_cod_usu' => ['nullable', 'integer', 'min:1'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }
        $user->is_active = $data['is_active'] ?? $user->is_active;
        $user->department_id = $data['department_id'] ?? $user->department_id;
        $user->senior_cod_usu = array_key_exists('senior_cod_usu', $data)
            ? ($data['senior_cod_usu'] ?: null)
            : $user->senior_cod_usu;
        $user->save();

        $oldBranchIds = $this->normalizeBranchIds($user->branches()->pluck('branches.id')->all());
        $newBranchIds = $this->normalizeBranchIds($data['branch_ids'] ?? []);
        $user->branches()->sync($newBranchIds);

        if ($oldBranchIds !== $newBranchIds) {
            AuditLogger::log(
                event: 'usuarios.filiais_atualizadas',
                module: 'usuarios',
                description: "Filiais de {$user->name} atualizadas",
                auditable: $user,
                oldValues: ['branch_ids' => $oldBranchIds],
                newValues: ['branch_ids' => $newBranchIds],
            );
        }

        return redirect('/usuarios')->with('success', 'Usuário atualizado com sucesso.');
    }

    /** @param array<int, mixed> $ids */
    private function normalizeBranchIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return array<int, array{id:int,name:string,code:?string}> */
    private function branchOptions(): array
    {
        return Branch::where('is_active', true)
            ->get(['id', 'name', 'code', 'cnpj'])
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->display_name,
                'code' => $b->code,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function toggleActive(int $id, Request $request)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Você não pode desativar a si mesmo.');
        }

        $previous = $user->is_active;
        $user->is_active = !$user->is_active;
        $user->save();

        AuditLogger::log(
            event: 'usuarios.toggle_active',
            module: 'usuarios',
            description: ($user->is_active ? 'Ativou' : 'Inativou') . " usuário {$user->name}",
            auditable: $user,
            oldValues: ['is_active' => $previous],
            newValues: ['is_active' => $user->is_active],
        );

        return back()->with('success', $user->is_active ? 'Usuário ativado.' : 'Usuário inativado.');
    }

    public function unlock(int $id, Request $request)
    {
        $user = User::findOrFail($id);

        if (!$user->locked_until && !$user->failed_login_attempts) {
            return back()->with('success', 'Usuário não está bloqueado.');
        }

        $user->failed_login_attempts = 0;
        $user->last_failed_login_at = null;
        $user->locked_until = null;
        $user->saveQuietly();

        AuditLogger::log(
            event: 'usuarios.unlock',
            module: 'usuarios',
            description: "Desbloqueou usuário {$user->name}",
            auditable: $user,
        );

        return back()->with('success', 'Usuário desbloqueado.');
    }

    public function destroy(int $id, Request $request)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Você não pode excluir a si mesmo.');
        }

        $user->delete();
        return redirect('/usuarios')->with('success', 'Usuário excluído.');
    }
}
