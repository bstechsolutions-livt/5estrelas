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
        'data_aprovacao' => 'date',
        'postos' => 'array',
        'identificacao' => 'array',
        'total_mensal' => 'decimal:2',
        'total_anual' => 'decimal:2',
        'va_total' => 'decimal:2',
        'valor' => 'decimal:2',
        'valor_aprovado' => 'decimal:2',
        'da_cotacao' => 'boolean',
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

    // ─── Situação (Controle de Propostas) ───────────────────────────────────────
    public const SITUACAO_LABELS = [
        'EM ANÁLISE' => 'Em Análise',
        'APROVADO' => 'Aprovado',
        'REPROVADO' => 'Reprovado',
        'ESTIMATIVA' => 'Estimativa',
        'REDUÇÃO' => 'Redução',
    ];

    /** Situações válidas (valores aceitos do protótipo). */
    public static function situacoesValidas(): array
    {
        return array_keys(self::SITUACAO_LABELS);
    }

    /** Classe de badge (g360) para a situação atual. */
    public function situacaoBadgeClass(): string
    {
        return match ($this->situacao) {
            'APROVADO' => 'badge-green',
            'REPROVADO' => 'badge-red',
            'EM ANÁLISE' => 'badge-blue',
            'ESTIMATIVA', 'REDUÇÃO' => 'badge-orange',
            default => 'badge-blue',
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

    /**
     * Numeração automática no formato do protótipo "Nº {N}".
     *
     * Regra (igual ao getProximoNum do protótipo): base 131 (histórico importado vai
     * até 131). Procura o menor número livre acima de 131 — propostas excluídas
     * liberam o número para reutilização. Garante unicidade.
     */
    public static function gerarNumero(): string
    {
        $base = 131;

        // Coleta todos os números numéricos em uso.
        $usados = [];
        foreach (static::pluck('numero') as $numero) {
            if (preg_match('/(\d+)/', (string) $numero, $m)) {
                $n = (int) $m[1];
                if ($n > 0) {
                    $usados[$n] = true;
                }
            }
        }

        $max = empty($usados) ? $base : max(array_keys($usados));

        // Procura o menor número livre a partir de base+1 (número liberado por exclusão).
        for ($n = $base + 1; $n <= $max; $n++) {
            if (! isset($usados[$n])) {
                return self::formatarNumero($n);
            }
        }

        $proximo = $max + 1;

        // Garante unicidade mesmo diante de formatos inesperados de `numero`.
        while (static::where('numero', self::formatarNumero($proximo))->exists()) {
            $proximo++;
        }

        return self::formatarNumero($proximo);
    }

    private static function formatarNumero(int $seq): string
    {
        return 'Nº '.$seq;
    }
}
