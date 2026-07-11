<?php

namespace App\Models\Comercial;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Comercial — Filial / Empresa do grupo, ESPELHADA da Senior ERP.
 *
 * Fonte da verdade = Senior (serviço cad_filial / mapa de empresas codEmp).
 * Populada pelo sync `senior:sync-filiais` (FiliaisSyncService). Os campos
 * tipo/tag são apresentação local; cod_emp/cod_fil/cnpj/nome vêm da Senior.
 */
class Filial extends Model
{
    use Auditable;

    protected $table = 'bs_comercial_filiais';

    protected $guarded = [];

    protected $casts = [
        'cod_emp' => 'integer',
        'cod_fil' => 'integer',
        'ativo' => 'boolean',
        'ordem' => 'integer',
        'senior_raw' => 'array',
        'senior_synced_at' => 'datetime',
    ];

    /** Rótulo curto para exibição (apelido local, não vem da Senior). */
    protected $appends = ['label', 'codigo'];

    /** Não expõe o payload bruto da Senior nas respostas JSON. */
    protected $hidden = ['senior_raw'];

    public function getLabelAttribute(): string
    {
        return $this->apelido ?: $this->fantasia ?: $this->nome;
    }

    /** Gera apelido a partir do nome/fantasia/tag. */
    public static function gerarApelido(?string $nome, ?string $fantasia = null, ?string $tag = null): string
    {
        if (filled($tag)) {
            return mb_substr(trim($tag), 0, 100);
        }

        $base = trim($fantasia ?: $nome ?: '');
        if ($base === '') {
            return 'Filial';
        }

        $base = preg_replace('/\s+(LTDA|EIRELI|S\.?A\.?|ME|EPP|S\/A)\.?$/iu', '', $base) ?? $base;
        $base = preg_replace('/\s+SERVI(CO|Ç)OS.*$/iu', '', $base) ?? $base;
        $base = trim($base);

        if (mb_strlen($base) > 25) {
            $words = preg_split('/\s+/', $base) ?: [];
            $short = $words[0] ?? $base;
            if (isset($words[1]) && mb_strlen($short.' '.$words[1]) <= 25) {
                $short .= ' '.$words[1];
            }
            $base = $short;
        }

        return mb_substr($base, 0, 100) ?: 'Filial';
    }

    /** Valor (string) usado nos selects de empresa — o cod_emp da Senior como string. */
    public function getCodigoAttribute(): ?string
    {
        return $this->cod_emp !== null ? (string) $this->cod_emp : null;
    }

    /** Opções para selects (apelido + cod_emp). */
    public static function selectOptions(): array
    {
        return static::query()
            ->where('ativo', true)
            ->orderBy('apelido')
            ->orderBy('nome')
            ->get(['cod_emp', 'apelido', 'nome', 'fantasia'])
            ->map(fn (self $f) => [
                'label' => $f->label,
                'value' => $f->cod_emp,
            ])
            ->values()
            ->all();
    }

    // ─── Auditoria ────────────────────────────────────────────────────────────
    protected string $auditableModule = 'comercial';
    protected string $auditableEventPrefix = 'comercial_filial';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Filial {$this->nome} criada",
            'updated' => "Filial {$this->nome} atualizada",
            'deleted' => "Filial {$this->nome} excluída",
            default => null,
        };
    }

    // ─── Classificação local (apresentação) ─────────────────────────────────────
    public const TIPO_LABELS = [
        'seguranca' => 'Segurança',
        'apoio' => 'Apoio / Serviços',
    ];

    public static function tiposValidos(): array
    {
        return array_keys(self::TIPO_LABELS);
    }

    /**
     * Business key Senior: "codEmp-codFil". Null quando faltar codEmp.
     */
    public static function businessKey(?int $codEmp, ?int $codFil): ?string
    {
        if ($codEmp === null) {
            return null;
        }

        return $codEmp . '-' . ($codFil ?? 1);
    }
}
