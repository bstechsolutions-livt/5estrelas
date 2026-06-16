<?php

namespace App\Models\Comercial;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comercial — Reajuste de contrato (esteira de reajuste anual por cliente).
 * Porte 1:1 da estrutura do protótipo Gestão 360º (SEED_REAJUSTES).
 */
class Reajuste extends Model
{
    use Auditable;

    protected $table = 'bs_comercial_reajustes';

    protected $guarded = [];

    protected $casts = [
        'pct' => 'decimal:2',
        'valor_atual' => 'decimal:2',
        'impacto_mensal' => 'decimal:2',
        'data_ref' => 'date',
        'data_criacao' => 'date',
        'historico' => 'array',
        'itens' => 'array',
    ];

    // ─── Auditoria ────────────────────────────────────────────────────────────
    protected string $auditableModule = 'comercial';
    protected string $auditableEventPrefix = 'comercial_reajuste';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Reajuste de {$this->cliente_nome} criado ({$this->pct}%)",
            'updated' => "Reajuste de {$this->cliente_nome} atualizado",
            'deleted' => "Reajuste de {$this->cliente_nome} excluído",
            default => null,
        };
    }

    // ─── Status (do protótipo) ──────────────────────────────────────────────────
    public const STATUS_LABELS = [
        'pendente' => 'Pendente',
        'calculado' => 'Calculado',
        'enviado' => 'Enviado',
        'aprovado' => 'Aprovado',
        'recusado' => 'Recusado',
    ];

    public static function statusValidos(): array
    {
        return array_keys(self::STATUS_LABELS);
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            'aprovado' => 'badge-green',
            'recusado' => 'badge-red',
            'enviado' => 'badge-gold',
            'calculado' => 'badge-blue',
            default => 'badge-orange',
        };
    }

    // ─── Relations ──────────────────────────────────────────────────────────────
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
