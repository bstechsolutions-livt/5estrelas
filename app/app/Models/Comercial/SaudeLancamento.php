<?php

namespace App\Models\Comercial;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comercial — Saúde Contratual: lançamento mensal de um contrato/cliente.
 * Guarda faturamento real, custos e inadimplência para o cálculo de margem/score.
 */
class SaudeLancamento extends Model
{
    use Auditable;

    protected $table = 'bs_comercial_saude_lancamentos';

    protected $guarded = [];

    protected $casts = [
        'faturamento_real' => 'decimal:2',
        'custo_folha' => 'decimal:2',
        'custo_beneficios' => 'decimal:2',
        'custo_insumos' => 'decimal:2',
        'inadimplencia' => 'decimal:2',
    ];

    protected string $auditableModule = 'comercial';
    protected string $auditableEventPrefix = 'comercial_saude';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Lançamento saúde {$this->mes_ref} criado para {$this->cliente?->nome}",
            'updated' => "Lançamento saúde {$this->mes_ref} atualizado",
            'deleted' => "Lançamento saúde {$this->mes_ref} excluído",
            default => null,
        };
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Custo total do mês. */
    public function custoTotal(): float
    {
        return round(
            (float) $this->custo_folha + (float) $this->custo_beneficios + (float) $this->custo_insumos,
            2,
        );
    }

    /** Resultado bruto do mês (faturamento - custo). */
    public function resultado(): float
    {
        return round((float) $this->faturamento_real - $this->custoTotal(), 2);
    }

    /** Margem % do mês. */
    public function margem(): float
    {
        $fat = (float) $this->faturamento_real;

        return $fat > 0 ? round($this->resultado() / $fat * 100, 2) : 0;
    }
}
