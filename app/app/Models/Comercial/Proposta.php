<?php

namespace App\Models\Comercial;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comercial — Proposta. Snapshot persistido de uma cotação montada na tela Nova Cotação.
 */
class Proposta extends Model
{
    use Auditable;

    protected $table = 'bs_comercial_propostas';

    protected $guarded = [];

    protected $casts = [
        'data_proposta' => 'date',
        'postos' => 'array',
        'identificacao' => 'array',
        'total_mensal' => 'decimal:2',
        'total_anual' => 'decimal:2',
        'va_total' => 'decimal:2',
    ];

    // ─── Auditoria ────────────────────────────────────────────────────────────
    protected string $auditableModule = 'comercial';
    protected string $auditableEventPrefix = 'comercial_proposta';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Proposta {$this->numero} criada - {$this->cliente} R$ {$this->total_mensal}",
            'updated' => "Proposta {$this->numero} atualizada",
            'deleted' => "Proposta {$this->numero} excluída",
            default => null,
        };
    }

    // ─── Status ───────────────────────────────────────────────────────────────
    public const STATUS_LABELS = [
        'rascunho' => 'Rascunho',
        'enviada' => 'Enviada',
        'aprovada' => 'Aprovada',
        'reprovada' => 'Reprovada',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────────
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Numeração automática sequencial no formato "PRP-XXXX" (zero-padded a 4).
     * Baseia-se no maior sufixo numérico já existente (max+1). Garante unicidade
     * mesmo diante de concorrência simples ou formatos inesperados de `numero`.
     */
    public static function gerarNumero(): string
    {
        $maior = 0;
        foreach (static::pluck('numero') as $numero) {
            if (preg_match('/(\d+)/', (string) $numero, $m)) {
                $maior = max($maior, (int) $m[1]);
            }
        }

        $proximo = $maior + 1;

        // Garante unicidade mesmo diante de formatos inesperados de `numero`.
        while (static::where('numero', self::formatarNumero($proximo))->exists()) {
            $proximo++;
        }

        return self::formatarNumero($proximo);
    }

    private static function formatarNumero(int $seq): string
    {
        return 'PRP-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
