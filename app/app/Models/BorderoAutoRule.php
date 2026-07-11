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

    /** Ordem canônica dos critérios de agrupamento. */
    public const GROUP_BY_ORDER = [
        'empresa',
        'filial',
        'departamento',
        'ccu',
        'fornecedor',
        'natureza',
        'transacao',
        'tipo_titulo',
        'categoria',
    ];

    protected $fillable = [
        'name',
        'group_by',
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
        'group_by' => 'array',
        'is_active' => 'boolean',
        'min_titles_per_group' => 'integer',
        'max_due_span_days' => 'integer',
        'eligibility_due_days' => 'integer',
        'last_applied_at' => 'datetime',
        'last_applied_count' => 'integer',
        'last_cron_at' => 'datetime',
        'last_cron_count' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function borderos(): HasMany
    {
        return $this->hasMany(Bordero::class, 'auto_rule_id');
    }

    /** @return array<string, string> */
    public static function groupByLabels(): array
    {
        return [
            'empresa' => 'Empresa',
            'filial' => 'Filial (codFil)',
            'departamento' => 'Departamento (classificação)',
            'ccu' => 'Centro de custo (CCU)',
            'fornecedor' => 'Fornecedor',
            'natureza' => 'Natureza (codNtg)',
            'transacao' => 'Transação (codTns)',
            'tipo_titulo' => 'Tipo de título (codTpt)',
            'categoria' => 'Categoria (Hub)',
        ];
    }

    /** @return array<string, string> */
    public static function dueGroupingLabels(): array
    {
        return [
            self::DUE_NONE => 'Ignorar vencimento',
            self::DUE_SAME_DAY => 'Mesmo dia de vencimento',
            self::DUE_MAX_SPAN => 'Diferença máxima de dias',
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

    /** @return list<string> */
    public function normalizedGroupBy(): array
    {
        $selected = array_values(array_intersect(
            self::GROUP_BY_ORDER,
            is_array($this->group_by) ? $this->group_by : [],
        ));

        return $selected !== [] ? $selected : ['empresa', 'departamento'];
    }

    /** @return array<string, mixed> */
    public function toFormArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'group_by' => $this->normalizedGroupBy(),
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
        $labels = self::groupByLabels();
        $groupLine = 'Agrupar por: ' . implode(' → ', array_map(
            fn (string $key) => $labels[$key] ?? $key,
            $this->normalizedGroupBy(),
        ));

        $lines = [
            $groupLine,
            self::eligibilityLabels()[$this->eligibility_mode] ?? $this->eligibility_mode,
        ];

        if ($this->eligibility_mode === self::ELIGIBILITY_DUE_WITHIN && $this->eligibility_due_days) {
            $lines[] = "Janela: até {$this->eligibility_due_days} dias a partir de hoje.";
        }

        $lines[] = self::dueGroupingLabels()[$this->due_grouping] ?? $this->due_grouping;

        if ($this->due_grouping === self::DUE_MAX_SPAN) {
            $lines[] = "Diferença máxima: {$this->max_due_span_days} dias.";
        }

        $lines[] = "Mínimo de {$this->min_titles_per_group} título(s) por borderô.";

        return $lines;
    }

    /** Instância temporária a partir dos dados do formulário (simulação). */
    public static function fromPayload(array $data): self
    {
        $rule = new self;
        $rule->fill([
            'name' => $data['name'] ?? 'Nova regra',
            'group_by' => $data['group_by'] ?? ['empresa', 'departamento'],
            'min_titles_per_group' => $data['min_titles_per_group'] ?? 2,
            'due_grouping' => $data['due_grouping'] ?? self::DUE_NONE,
            'max_due_span_days' => $data['max_due_span_days'] ?? 7,
            'eligibility_mode' => $data['eligibility_mode'] ?? self::ELIGIBILITY_ALL,
            'eligibility_due_days' => $data['eligibility_due_days'] ?? null,
        ]);

        return $rule;
    }
}
