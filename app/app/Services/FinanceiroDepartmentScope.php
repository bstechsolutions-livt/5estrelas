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

    /** Locked department id for scoped users; null = all departments. */
    public function resolve(User $user): ?int
    {
        if ($this->canBypass($user)) {
            return null;
        }

        return $user->department_id ? (int) $user->department_id : null;
    }

    /**
     * @return array{
     *     department_id: ?int,
     *     can_change: bool,
     *     locked_department: ?array{id:int,name:string}
     * }
     */
    public function resolveFilter(Request $request): array
    {
        $user = $request->user();
        $canChange = $this->canBypass($user);

        if ($canChange) {
            $departmentId = $request->filled('department_id') ? (int) $request->department_id : null;

            return [
                'department_id' => $departmentId ?: null,
                'can_change' => true,
                'locked_department' => null,
            ];
        }

        $departmentId = $user?->department_id ? (int) $user->department_id : null;
        $locked = $departmentId
            ? Department::whereKey($departmentId)->first(['id', 'name'])
            : null;

        return [
            'department_id' => $departmentId,
            'can_change' => false,
            'locked_department' => $locked ? ['id' => $locked->id, 'name' => $locked->name] : null,
        ];
    }

    public function applyFilter(Builder $query, ?int $departmentId): void
    {
        if ($departmentId) {
            app(PayableDepartmentClassifier::class)->applyDepartmentFilter($query, $departmentId);
        }
    }

    public function applyPayableFilter(Builder $query, User $user): void
    {
        $this->applyFilter($query, $this->resolve($user));
    }

    public function applyBorderoFilter(Builder $query, User $user): void
    {
        $departmentId = $this->resolve($user);
        if (!$departmentId) {
            return;
        }

        $query->whereHas('payables', fn (Builder $q) => $this->applyFilter($q, $departmentId));
    }
}
