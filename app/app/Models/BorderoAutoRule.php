<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorderoAutoRule extends Model
{
    public const DUE_NONE = 'none';

    public const DUE_SAME_DAY = 'same_day';

    public const DUE_MAX_SPAN = 'max_span';

    public const ELIGIBILITY_ALL = 'all_pending';

    public const ELIGIBILITY_DUE_WITHIN = 'due_within_days';

    protected $fillable = [
        'name',
        'filters',
        'filter_logic',
        'is_active',
        'min_titles_per_group',
        'due_grouping',
        'max_due_span_days',
        'eligibility_mode',
        'eligibility_due_days',
        'created_by',
        'last_applied_at',
        'last_applied_count',
        'last_cron_at',
        'last_cron_count',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_active' => 'boolean',
        'min_titles_per_group' => 'integer',
        'max_due_span_days' => 'integer',
        'eligibility_due_days' => 'integer',
        'last_applied_at' => 'datetime',
        'last_applied_count' => 'integer',
        'last_cron_at' => 'datetime',
        'last_cron_count' => 'integer',
    ];

    /** @return array<string, array{label: string, operators: list<string>}> */
    public static function filterFields(): array
    {
        return [
            'codemp' => ['label' => 'Empresa', 'operators' => ['eq', 'in']],
            'codfil' => ['label' => 'Filial (codFil)', 'operators' => ['eq', 'in']],
            'department_id' => ['label' => 'Departamento', 'operators' => ['eq']],
            'codccu' => ['label' => 'Centro de custo (CCU)', 'operators' => ['eq', 'in']],
            'codntg' => ['label' => 'Natureza (codNtg)', 'operators' => ['eq', 'in']],
            'codtns' => ['label' => 'Transação (codTns)', 'operators' => ['eq', 'in']],
            'codtpt' => ['label' => 'Tipo de título (codTpt)', 'operators' => ['eq', 'in']],
            'codfor' => ['label' => 'Código fornecedor (codFor)', 'operators' => ['eq', 'in']],
            'supplier_cnpj' => ['label' => 'CNPJ fornecedor', 'operators' => ['eq']],
            'supplier_name' => ['label' => 'Nome fornecedor', 'operators' => ['eq', 'contains']],
            'category' => ['label' => 'Categoria (Hub)', 'operators' => ['eq', 'contains']],
        ];
    }

    public static function filterColumn(string $field): ?string
    {
        return match ($field) {
            'codemp' => 'codemp',
            'codfil' => 'codfil',
            'codccu' => 'codccu',
            'codntg' => 'codntg',
            'codtns' => 'codtns',
            'codtpt' => 'codtpt',
            'codfor' => 'codfor',
            'supplier_cnpj' => 'supplier_cnpj',
            'supplier_name' => 'supplier_name',
            'category' => 'category',
            default => null,
        };
    }

    public static function operatorLabels(): array
    {
        return [
            'eq' => 'é igual a',
            'in' => 'está em (vírgula)',
            'contains' => 'contém',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function borderos(): HasMany
    {
        return $this->hasMany(Bordero::class, 'auto_rule_id');
    }

    /** @return array<string, string> */
    public static function dueGroupingLabels(): array
    {
        return [
            self::DUE_NONE => 'Um borderô com todos os títulos',
            self::DUE_SAME_DAY => 'Separar por mesmo dia de vencimento',
            self::DUE_MAX_SPAN => 'Separar por diferença máxima de dias',
        ];
    }

    /** @return array<string, string> */
    public static function eligibilityLabels(): array
    {
        return [
            self::ELIGIBILITY_ALL => 'Todos os pendentes elegíveis',
            self::ELIGIBILITY_DUE_WITHIN => 'Vencimento até N dias (inclui vencidos)',
        ];
    }

    /** @return list<array{field: string, operator: string, value: string}> */
    public function normalizedFilters(): array
    {
        $fields = self::filterFields();
        $normalized = [];

        foreach (is_array($this->filters) ? $this->filters : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $field = (string) ($row['field'] ?? '');
            $operator = (string) ($row['operator'] ?? 'eq');
            $value = trim((string) ($row['value'] ?? ''));

            if (! isset($fields[$field]) || $value === '') {
                continue;
            }

            if (! in_array($operator, $fields[$field]['operators'], true)) {
                $operator = $fields[$field]['operators'][0];
            }

            $normalized[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => $value,
            ];
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    public function toFormArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'filters' => $this->normalizedFilters(),
            'filter_logic' => $this->filter_logic === 'or' ? 'or' : 'and',
            'is_active' => $this->is_active,
            'min_titles_per_group' => $this->min_titles_per_group,
            'due_grouping' => $this->due_grouping,
            'max_due_span_days' => $this->max_due_span_days,
            'eligibility_mode' => $this->eligibility_mode,
            'eligibility_due_days' => $this->eligibility_due_days ?? 30,
        ];
    }

    /** @return list<string> */
    public function rulesSummary(): array
    {
        $fieldLabels = collect(self::filterFields())->mapWithKeys(fn ($m, $k) => [$k => $m['label']]);
        $opLabels = self::operatorLabels();
        $filters = $this->normalizedFilters();

        $lines = [];
        if ($filters === []) {
            $lines[] = 'Sem condições específicas (pega todos os elegíveis).';
        } else {
            $joiner = $this->filter_logic === 'or' ? ' OU ' : ' E ';
            $parts = array_map(function (array $f) use ($fieldLabels, $opLabels) {
                $label = $fieldLabels[$f['field']] ?? $f['field'];
                $op = $opLabels[$f['operator']] ?? $f['operator'];

                return "{$label} {$op} {$f['value']}";
            }, $filters);
            $lines[] = 'Quando: ' . implode($joiner, $parts);
        }

        $lines[] = self::eligibilityLabels()[$this->eligibility_mode] ?? $this->eligibility_mode;

        if ($this->eligibility_mode === self::ELIGIBILITY_DUE_WITHIN && $this->eligibility_due_days) {
            $lines[] = "Janela: até {$this->eligibility_due_days} dias.";
        }

        $lines[] = self::dueGroupingLabels()[$this->due_grouping] ?? $this->due_grouping;

        if ($this->due_grouping === self::DUE_MAX_SPAN) {
            $lines[] = "Diferença máxima: {$this->max_due_span_days} dias.";
        }

        $lines[] = "Mínimo de {$this->min_titles_per_group} título(s) por borderô.";

        return $lines;
    }

    public static function fromPayload(array $data): self
    {
        $rule = new self;
        $rule->fill([
            'name' => $data['name'] ?? 'Nova regra',
            'filters' => $data['filters'] ?? [],
            'filter_logic' => ($data['filter_logic'] ?? 'and') === 'or' ? 'or' : 'and',
            'min_titles_per_group' => $data['min_titles_per_group'] ?? 2,
            'due_grouping' => $data['due_grouping'] ?? self::DUE_NONE,
            'max_due_span_days' => $data['max_due_span_days'] ?? 7,
            'eligibility_mode' => $data['eligibility_mode'] ?? self::ELIGIBILITY_ALL,
            'eligibility_due_days' => $data['eligibility_due_days'] ?? null,
        ]);

        return $rule;
    }
}
