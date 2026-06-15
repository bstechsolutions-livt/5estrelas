<?php

namespace App\Models\Comercial;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comercial — Cliente. Entidade que agrega propostas, contratos, postos e valor mensal.
 */
class Cliente extends Model
{
    use Auditable;

    protected $table = 'bs_comercial_clientes';

    protected $guarded = [];

    protected $casts = [
        'valor_mensal' => 'decimal:2',
    ];

    // ─── Auditoria ────────────────────────────────────────────────────────────
    protected string $auditableModule = 'comercial';
    protected string $auditableEventPrefix = 'comercial_cliente';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Cliente {$this->nome} criado",
            'updated' => "Cliente {$this->nome} atualizado",
            'deleted' => "Cliente {$this->nome} excluído",
            default => null,
        };
    }

    // ─── Situação ─────────────────────────────────────────────────────────────
    public const SITUACAO_LABELS = [
        'ativo' => 'Ativo',
        'inativo' => 'Inativo',
        'prospecto' => 'Prospecto',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────────
    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class, 'cliente_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
