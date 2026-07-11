<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorderoAutoConfig extends Model
{
    public const DUE_NONE = 'none';

    public const DUE_SAME_DAY = 'same_day';

    public const DUE_MAX_SPAN = 'max_span';

    public const ELIGIBILITY_ALL = 'all_pending';

    public const ELIGIBILITY_DUE_WITHIN = 'due_within_days';

    protected $fillable = [
        'min_titles_per_group',
        'due_grouping',
        'max_due_span_days',
        'eligibility_mode',
        'eligibility_due_days',
        'cron_enabled',
        'last_cron_run_at',
        'last_cron_created_count',
    ];

    protected $casts = [
        'min_titles_per_group' => 'integer',
        'max_due_span_days' => 'integer',
        'eligibility_due_days' => 'integer',
        'cron_enabled' => 'boolean',
        'last_cron_run_at' => 'datetime',
        'last_cron_created_count' => 'integer',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'min_titles_per_group' => 2,
                'due_grouping' => self::DUE_NONE,
                'max_due_span_days' => 7,
                'eligibility_mode' => self::ELIGIBILITY_ALL,
                'eligibility_due_days' => null,
                'cron_enabled' => true,
            ],
        );
    }

    /** @return array<string, string> */
    public static function dueGroupingLabels(): array
    {
        return [
            self::DUE_NONE => 'Ignorar vencimento (todos os elegíveis no mesmo grupo)',
            self::DUE_SAME_DAY => 'Mesmo dia de vencimento',
            self::DUE_MAX_SPAN => 'Diferença máxima de dias entre vencimentos',
        ];
    }

    /** @return array<string, string> */
    public static function eligibilityLabels(): array
    {
        return [
            self::ELIGIBILITY_ALL => 'Todos os títulos pendentes elegíveis',
            self::ELIGIBILITY_DUE_WITHIN => 'Só títulos com vencimento até N dias à frente (inclui vencidos)',
        ];
    }

    /** Texto legível do que está configurado agora. */
    public function rulesSummary(): array
    {
        $lines = [
            'Separar por empresa (codEmp).',
            'Dentro da empresa: departamento, ou CCU, ou sem classificação.',
            self::eligibilityLabels()[$this->eligibility_mode] ?? $this->eligibility_mode,
        ];

        if ($this->eligibility_mode === self::ELIGIBILITY_DUE_WITHIN && $this->eligibility_due_days) {
            $lines[] = "Janela de elegibilidade: até {$this->eligibility_due_days} dias a partir de hoje.";
        }

        $lines[] = self::dueGroupingLabels()[$this->due_grouping] ?? $this->due_grouping;

        if ($this->due_grouping === self::DUE_MAX_SPAN) {
            $lines[] = "Diferença máxima entre vencimentos no grupo: {$this->max_due_span_days} dias.";
        }

        $lines[] = "Mínimo de {$this->min_titles_per_group} título(s) por borderô.";

        if ($this->cron_enabled) {
            $lines[] = 'Cron diário às 06:00 gera automaticamente todos os grupos abaixo em rascunho.';
        } else {
            $lines[] = 'Cron automático desligado — geração só manual nesta tela.';
        }

        return $lines;
    }
}
