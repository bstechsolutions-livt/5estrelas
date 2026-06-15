<?php

namespace App\Models\Comercial;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comercial — Faturamento mensal por local/contrato.
 */
class Faturamento extends Model
{
    use Auditable;

    protected $table = 'bs_comercial_faturamento';

    protected $guarded = [];

    protected $casts = [
        'ano' => 'integer',
        'jan' => 'decimal:2',
        'fev' => 'decimal:2',
        'mar' => 'decimal:2',
        'abr' => 'decimal:2',
        'mai' => 'decimal:2',
        'jun' => 'decimal:2',
        'jul' => 'decimal:2',
        'ago' => 'decimal:2',
        'setembro' => 'decimal:2',
        'out' => 'decimal:2',
        'nov' => 'decimal:2',
        'dez' => 'decimal:2',
    ];

    // ─── Auditoria ────────────────────────────────────────────────────────────
    protected string $auditableModule = 'comercial';
    protected string $auditableEventPrefix = 'comercial_faturamento';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Faturamento '{$this->local_nome}' ({$this->ano}) criado",
            'updated' => "Faturamento '{$this->local_nome}' ({$this->ano}) atualizado",
            'deleted' => "Faturamento '{$this->local_nome}' ({$this->ano}) excluído",
            default => null,
        };
    }

    // ─── Meses (nomes de coluna) ──────────────────────────────────────────────
    public const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'setembro', 'out', 'nov', 'dez'];

    // ─── Relations ──────────────────────────────────────────────────────────────
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────
    /**
     * Soma dos 12 meses.
     */
    public function total(): float
    {
        $soma = 0;
        foreach (self::MESES as $mes) {
            $soma += (float) $this->{$mes};
        }
        return round($soma, 2);
    }
}
