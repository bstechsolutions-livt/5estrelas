<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDepartmentRule;
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
        if ($payable->department_id) {
            return Department::whereKey($payable->department_id)->value('slug');
        }

        $codccu = trim((string) ($payable->codccu ?? ''));
        $description = (string) ($payable->description ?? '');

        foreach ($this->rules() as $slug => $rule) {
            if ($codccu !== '' && in_array($codccu, $rule['codccu'] ?? [], true)) {
                return $slug;
            }
            foreach ($rule['description'] ?? [] as $pattern) {
                if ($this->descriptionMatches($description, $pattern)) {
                    return $slug;
                }
            }
        }

        return null;
    }

    public function departmentForPayable(Payable $payable): ?Department
    {
        if ($payable->relationLoaded('department') && $payable->department) {
            return $payable->department;
        }

        if ($payable->department_id) {
            return Department::find($payable->department_id);
        }

        $slug = $this->slugForPayable($payable);

        return $slug ? Department::where('slug', $slug)->where('is_active', true)->first() : null;
    }

    /** Restringe a query aos títulos do departamento (workflow + regras Senior). */
    public function applyDepartmentFilter(Builder $query, int $departmentId): void
    {
        $department = Department::find($departmentId);
        if (!$department) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $q) use ($department) {
            $q->where('department_id', $department->id);
            $q->orWhere(function (Builder $inner) use ($department) {
                $inner->whereNull('department_id');
                $this->applyRuleMatch($inner, $department->slug);
            });
        });
    }

    public function applyRuleMatch(Builder $query, string $slug): void
    {
        $rule = $this->rules()[$slug] ?? null;
        if (!$rule) {
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

    private function descriptionMatches(string $description, string $pattern): bool
    {
        $needle = trim($pattern, '%');
        if ($needle === '') {
            return false;
        }

        return stripos($description, $needle) !== false;
    }
}
