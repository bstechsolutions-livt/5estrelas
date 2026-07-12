<?php

namespace App\Services;

use App\Models\BorderoAutoRule;
use App\Models\Comercial\Filial;
use App\Models\Department;
use App\Models\Payable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BorderoAutoRuleFilterService
{
    public function __construct(
        private PayableBranchScope $branchScope,
        private PayableDepartmentClassifier $classifier,
    ) {}

    /** @return Builder<Payable> */
    public function baseOpenQuery(?User $user): Builder
    {
        $query = Payable::query()
            ->whereNull('bordero_id')
            ->whereIn('status', ['pendente', 'em_preparacao']);

        if ($user) {
            $this->branchScope->applyFilter($query, $user);
        }

        return $query;
    }

    /** @return Builder<Payable> */
    public function applyRule(BorderoAutoRule $rule, Builder $query): Builder
    {
        if ($rule->eligibility_mode === BorderoAutoRule::ELIGIBILITY_DUE_WITHIN) {
            $days = max(1, (int) ($rule->eligibility_due_days ?? 30));
            $limit = now()->startOfDay()->addDays($days)->toDateString();
            $query->whereNotNull('due_date')->whereDate('due_date', '<=', $limit);
        }

        $filters = $rule->normalizedFilters();
        if ($filters === []) {
            return $query;
        }

        $logic = $rule->filter_logic === 'or' ? 'or' : 'and';

        $query->where(function (Builder $outer) use ($filters, $logic) {
            foreach ($filters as $filter) {
                $callback = fn (Builder $q) => $this->applySingleFilter($q, $filter);
                $logic === 'or'
                    ? $outer->orWhere($callback)
                    : $outer->where($callback);
            }
        });

        return $query;
    }

    /** @param array{field: string, operator: string, value: string} $filter */
    private function applySingleFilter(Builder $query, array $filter): void
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = trim((string) ($filter['value'] ?? ''));

        if ($value === '' && $field !== 'department_id') {
            $query->whereRaw('0 = 1');

            return;
        }

        if ($field === 'department_id') {
            $this->classifier->applyDepartmentFilter($query, (int) $value);

            return;
        }

        $column = BorderoAutoRule::filterColumn($field);
        if (! $column) {
            $query->whereRaw('0 = 1');

            return;
        }

        match ($operator) {
            'in' => $query->whereIn($column, $this->parseList($value)),
            'contains' => $this->applyLike($query, $column, '%' . $value . '%'),
            default => $this->applyEquals($query, $column, $value, $field),
        };
    }

    private function applyEquals(Builder $query, string $column, string $value, string $field): void
    {
        if (in_array($field, ['codemp', 'codfil', 'codfor', 'codntg'], true)) {
            $query->where($column, (int) $value);

            return;
        }

        if ($field === 'supplier_cnpj') {
            $digits = preg_replace('/\D/', '', $value);
            if ($query->getConnection()->getDriverName() === 'pgsql') {
                $query->whereRaw("regexp_replace({$column}, '[^0-9]', '', 'g') = ?", [$digits]);
            } else {
                $query->where($column, $digits);
            }

            return;
        }

        $query->where($column, $value);
    }

    private function applyLike(Builder $query, string $column, string $pattern): void
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->where($column, 'ilike', $pattern);

            return;
        }

        $query->whereRaw("LOWER({$column}) LIKE LOWER(?)", [$pattern]);
    }

    /** @return list<string> */
    private function parseList(string $value): array
    {
        return array_values(array_filter(array_map(
            fn (string $v) => trim($v),
            preg_split('/[,;]+/', $value) ?: [],
        )));
    }

    /**
     * @return array{
     *   field: string,
     *   label: string,
     *   operators: list<string>,
     *   options: list<array{value: string, label: string}>
     * }
     */
    public function fieldOptions(?User $user, string $field): array
    {
        $meta = BorderoAutoRule::filterFields()[$field] ?? null;
        if (! $meta) {
            return ['field' => $field, 'label' => $field, 'operators' => ['eq'], 'options' => []];
        }

        return [
            'field' => $field,
            'label' => $meta['label'],
            'operators' => $meta['operators'],
            'options' => match ($field) {
                'codemp' => $this->empresaOptions($user),
                'department_id' => $this->departmentOptions(),
                default => $this->distinctOptions($user, BorderoAutoRule::filterColumn($field)),
            },
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function empresaOptions(?User $user): array
    {
        if ($user) {
            $options = $this->branchScope->empresaOptionsForUser($user);
        } else {
            $options = Filial::selectOptions();
        }

        return array_map(fn (array $row) => [
            'value' => (string) $row['value'],
            'label' => $row['label'],
        ], $options);
    }

    /** @return list<array{value: string, label: string}> */
    private function departmentOptions(): array
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $d) => [
                'value' => (string) $d->id,
                'label' => $d->name,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function distinctOptions(?User $user, ?string $column): array
    {
        if (! $column) {
            return [];
        }

        $query = $this->baseOpenQuery($user)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column);

        if ($column === 'supplier_name') {
            $query->limit(200);
        } else {
            $query->limit(100);
        }

        return $query->pluck($column)
            ->map(fn ($v) => [
                'value' => (string) $v,
                'label' => (string) $v,
            ])
            ->values()
            ->all();
    }
}
