<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDepartmentRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PayableDepartmentClassifier
{
    /** @return array<string, array{codccu?: string[], description?: string[]}> */
    public function rules(): array
    {
        $fromDb = PayableDepartmentRule::query()
            ->with('department:id,slug')
            ->get()
            ->filter(fn (PayableDepartmentRule $rule) => $rule->department !== null)
            ->mapWithKeys(fn (PayableDepartmentRule $rule) => [
                $rule->department->slug => [
                    'codccu' => $rule->codccu ?? [],
                    'description' => $rule->description_patterns ?? [],
                ],
            ])
            ->all();

        if ($fromDb !== []) {
            return $fromDb;
        }

        return config('payables.department_rules', []);
    }

    public function slugForPayable(Payable $payable): ?string
    {
        return $this->departmentForPayable($payable)?->slug;
    }

    /**
     * Departamento do título: department_id explícito (fluxo) OU lançador Senior → usuário intranet.
     * Sem fallback por codCcu/descrição — se não achar, fica sem.
     */
    public function departmentForPayable(Payable $payable): ?Department
    {
        if ($payable->relationLoaded('department') && $payable->department) {
            return $payable->department->is_active ? $payable->department : null;
        }

        if ($payable->department_id) {
            $dept = Department::find($payable->department_id);

            return ($dept && $dept->is_active) ? $dept : null;
        }

        return $this->departmentFromSeniorCodUsu($payable->senior_cod_usu);
    }

    /** Restringe a query aos títulos do departamento (workflow + lançador Senior). Sem fallback. */
    public function applyDepartmentFilter(Builder $query, int $departmentId): void
    {
        $department = Department::find($departmentId);
        if (! $department) {
            $query->whereRaw('0 = 1');

            return;
        }

        $launcherCodUsus = User::query()
            ->where('department_id', $department->id)
            ->whereNotNull('senior_cod_usu')
            ->pluck('senior_cod_usu')
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        // Colunas qualificadas: joins (ex.: ordenação por aprovador junta users,
        // que também tem department_id/senior_cod_usu) tornam a referência ambígua.
        $table = $query->getModel()->getTable();

        $query->where(function (Builder $q) use ($table, $department, $launcherCodUsus) {
            $q->where("{$table}.department_id", $department->id);

            if ($launcherCodUsus !== []) {
                $q->orWhere(function (Builder $inner) use ($table, $launcherCodUsus) {
                    $inner->whereNull("{$table}.department_id")
                        ->whereIn("{$table}.senior_cod_usu", $launcherCodUsus);
                });
            }
        });
    }

    public function applyRuleMatch(Builder $query, string $slug): void
    {
        $rule = $this->rules()[$slug] ?? null;
        if (! $rule) {
            $query->whereRaw('0 = 1');

            return;
        }

        $codccus = $rule['codccu'] ?? [];
        $patterns = $rule['description'] ?? [];

        if ($codccus === [] && $patterns === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $q) use ($codccus, $patterns) {
            $applied = false;
            if ($codccus !== []) {
                $q->whereIn('codccu', $codccus);
                $applied = true;
            }
            foreach ($patterns as $pattern) {
                if ($applied) {
                    $this->applyDescriptionLike($q, $pattern, true);
                } else {
                    $this->applyDescriptionLike($q, $pattern, false);
                    $applied = true;
                }
            }
        });
    }

    private function departmentFromSeniorCodUsu(?int $codUsu): ?Department
    {
        if (! $codUsu || $codUsu <= 0) {
            return null;
        }

        $user = User::query()
            ->where('senior_cod_usu', $codUsu)
            ->whereNotNull('department_id')
            ->with('department:id,slug,name,is_active')
            ->first();

        if (! $user?->department || ! $user->department->is_active) {
            return null;
        }

        return $user->department;
    }

    private function applyDescriptionLike(Builder $query, string $pattern, bool $or = false): void
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $or
                ? $query->orWhere('description', 'ilike', $pattern)
                : $query->where('description', 'ilike', $pattern);

            return;
        }

        $or
            ? $query->orWhereRaw('LOWER(description) LIKE LOWER(?)', [$pattern])
            : $query->whereRaw('LOWER(description) LIKE LOWER(?)', [$pattern]);
    }
}
