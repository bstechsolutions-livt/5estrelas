<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FinanceiroDepartmentScope
{
    public const PERMISSION = 'financeiro.ver_todos_departamentos';

    /** @deprecated Prefer {@see self::PERMISSION}; kept for backward compatibility. */
    public const PERMISSION_CP_LEGACY = 'financeiro.contas_pagar.ver_todos_departamentos';

    public function canBypass(User $user): bool
    {
        return $user->hasPermission('*')
            || $user->hasPermission(self::PERMISSION)
            || $user->hasPermission(self::PERMISSION_CP_LEGACY);
    }

    /**
     * IDs de departamento que o usuário pode ver.
     * null = sem restrição (bypass ou usuário sem department_id e sem extras — legado).
     *
     * @return list<int>|null
     */
    public function allowedDepartmentIds(User $user): ?array
    {
        if ($this->canBypass($user)) {
            return null;
        }

        $ids = [];
        if ($user->department_id) {
            $ids[] = (int) $user->department_id;
        }

        $extraIds = $user->relationLoaded('extraDepartments')
            ? $user->extraDepartments->pluck('id')->all()
            : $user->extraDepartments()->pluck('departments.id')->all();

        foreach ($extraIds as $id) {
            $ids[] = (int) $id;
        }

        $ids = array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));

        if ($ids === []) {
            return null;
        }

        $activeIds = Department::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $activeIds === [] ? null : $activeIds;
    }

    /**
     * Departamento “de casa” quando o usuário está restrito a exatamente um;
     * null = bypass / sem restrição / múltiplos liberados.
     */
    public function resolve(User $user): ?int
    {
        $allowed = $this->allowedDepartmentIds($user);
        if ($allowed === null) {
            return null;
        }

        return count($allowed) === 1 ? $allowed[0] : null;
    }

    /**
     * @return array{
     *     department_id: ?int,
     *     department_ids: ?list<int>,
     *     can_change: bool,
     *     locked_department: ?array{id:int,name:string},
     *     allowed_departments: list<array{id:int,name:string}>
     * }
     */
    public function resolveFilter(Request $request): array
    {
        $user = $request->user();
        $canBypass = $this->canBypass($user);
        $allowedIds = $this->allowedDepartmentIds($user);
        $allowedDepartments = $this->departmentsPayload($allowedIds);

        if ($canBypass) {
            $departmentId = $request->filled('department_id') ? (int) $request->department_id : null;

            return [
                'department_id' => $departmentId ?: null,
                'department_ids' => null,
                'can_change' => true,
                'locked_department' => null,
                'allowed_departments' => $allowedDepartments,
            ];
        }

        if ($allowedIds === null) {
            return [
                'department_id' => null,
                'department_ids' => null,
                'can_change' => true,
                'locked_department' => null,
                'allowed_departments' => [],
            ];
        }

        $canChange = count($allowedIds) > 1;
        $requested = $request->filled('department_id') ? (int) $request->department_id : null;

        if ($canChange) {
            $departmentId = ($requested && in_array($requested, $allowedIds, true))
                ? $requested
                : null;

            return [
                'department_id' => $departmentId,
                'department_ids' => $departmentId ? [$departmentId] : $allowedIds,
                'can_change' => true,
                'locked_department' => null,
                'allowed_departments' => $allowedDepartments,
            ];
        }

        $onlyId = $allowedIds[0];
        $locked = Department::whereKey($onlyId)->first(['id', 'name']);

        return [
            'department_id' => $onlyId,
            'department_ids' => [$onlyId],
            'can_change' => false,
            'locked_department' => $locked ? ['id' => $locked->id, 'name' => $locked->name] : null,
            'allowed_departments' => $allowedDepartments,
        ];
    }

    public function applyFilter(Builder $query, ?int $departmentId): void
    {
        if ($departmentId) {
            app(PayableDepartmentClassifier::class)->applyDepartmentFilter($query, $departmentId);
        }
    }

    /** @param  list<int>|null  $departmentIds  null = sem filtro; lista = OR entre deptos */
    public function applyFilterForDepartments(Builder $query, ?array $departmentIds): void
    {
        if ($departmentIds === null) {
            return;
        }

        if ($departmentIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        if (count($departmentIds) === 1) {
            $this->applyFilter($query, $departmentIds[0]);

            return;
        }

        app(PayableDepartmentClassifier::class)->applyDepartmentFilterForIds($query, $departmentIds);
    }

    /**
     * Aplica o contexto de filtro (um depto selecionado ou união dos liberados).
     *
     * @param  array{department_id?: ?int, department_ids?: ?list<int>}  $context
     */
    public function applyFromContext(Builder $query, array $context): void
    {
        if (! empty($context['department_id'])) {
            $this->applyFilter($query, (int) $context['department_id']);

            return;
        }

        $this->applyFilterForDepartments($query, $context['department_ids'] ?? null);
    }

    public function applyPayableFilter(Builder $query, User $user): void
    {
        $this->applyFilterForDepartments($query, $this->allowedDepartmentIds($user));
    }

    public function applyBorderoFilter(Builder $query, User $user): void
    {
        $allowed = $this->allowedDepartmentIds($user);
        if ($allowed === null) {
            return;
        }

        $query->whereHas('payables', fn (Builder $q) => $this->applyFilterForDepartments($q, $allowed));
    }

    /**
     * @param  list<int>|null  $ids
     * @return list<array{id:int,name:string}>
     */
    private function departmentsPayload(?array $ids): array
    {
        if ($ids === null || $ids === []) {
            return [];
        }

        return Department::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $d) => ['id' => $d->id, 'name' => $d->name])
            ->values()
            ->all();
    }
}
