<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Branch extends Model
{
    use Auditable;

    protected $fillable = ['name', 'apelido', 'cnpj', 'code', 'cod_emp', 'cod_fil', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'cod_emp' => 'integer',
        'cod_fil' => 'integer',
    ];

    protected $appends = ['display_name'];

    /** Nome curto para exibição (apelido da filial espelhada na Senior, se houver). */
    public function getDisplayNameAttribute(): string
    {
        return $this->resolveDisplayName();
    }

    /** Nome curto para exibição: apelido local, depois vínculo Senior/CNPJ. */
    public function resolveDisplayName(): string
    {
        if (filled($this->apelido)) {
            return trim($this->apelido);
        }

        $filial = $this->resolveComercialFilial();
        if ($filial !== null) {
            $apelido = filled($filial->apelido) ? $filial->apelido : $filial->label;

            return $this->appendFilialSuffix($apelido);
        }

        return \App\Models\Comercial\Filial::gerarApelido($this->name);
    }

    /** Filial Senior espelhada (CNPJ, nome da empresa, cod_fil ou cod_emp). */
    public function resolveComercialFilial(): ?\App\Models\Comercial\Filial
    {
        $query = \App\Models\Comercial\Filial::query()->where('ativo', true);

        if ($this->cod_emp && $this->cod_fil) {
            $exact = (clone $query)
                ->where('cod_emp', (int) $this->cod_emp)
                ->where('cod_fil', (int) $this->cod_fil)
                ->first();
            if ($exact !== null) {
                return $exact;
            }
        }

        $cnpj = $this->normalizedCnpj();

        if ($cnpj !== null) {
            $byCnpj = (clone $query)->get()->first(
                fn (\App\Models\Comercial\Filial $f) => $this->cnpjMatches($f->cnpj, $cnpj),
            );
            if ($byCnpj !== null) {
                return $byCnpj;
            }
        }

        $byName = $this->matchComercialFilialByBranchName((clone $query)->get());
        if ($byName !== null) {
            return $byName;
        }

        if (! is_numeric($this->code)) {
            return null;
        }

        $code = (int) $this->code;
        $byCodFil = (clone $query)->where('cod_fil', $code)->get();

        if ($byCodFil->count() === 1) {
            return $byCodFil->first();
        }

        if ($byCodFil->count() > 1 && $cnpj !== null) {
            $match = $byCodFil->first(
                fn (\App\Models\Comercial\Filial $f) => $this->cnpjMatches($f->cnpj, $cnpj),
            );
            if ($match !== null) {
                return $match;
            }
        }

        if ($this->extractFilialSuffixFromName() === null) {
            $byCodEmp = (clone $query)->where('cod_emp', $code)->get();
            if ($byCodEmp->count() === 1) {
                return $byCodEmp->first();
            }
        }

        return null;
    }

    private function appendFilialSuffix(string $apelido): string
    {
        $suffix = $this->extractFilialSuffixFromName();
        if ($suffix === null) {
            return $apelido;
        }

        if (preg_match('/\b'.preg_quote($suffix, '/').'\b/iu', $apelido)) {
            return $apelido;
        }

        return trim($apelido.' '.$suffix);
    }

    private function extractFilialSuffixFromName(): ?string
    {
        if (preg_match('/FILIAL\s+([A-ZÀ-Ú]{2,})\b/iu', $this->name, $match)) {
            return mb_strtoupper(trim($match[1]));
        }

        if (preg_match('/\bMATRIZ\b/iu', $this->name)) {
            return 'MATRIZ';
        }

        if (preg_match('/\bGERENCIAL\b/iu', $this->name)) {
            return 'GERENCIAL';
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Comercial\Filial>  $filiais
     */
    private function matchComercialFilialByBranchName(\Illuminate\Support\Collection $filiais): ?\App\Models\Comercial\Filial
    {
        $name = $this->normalizeForMatch($this->name);
        $best = null;
        $bestLen = 0;

        foreach ($filiais as $filial) {
            foreach ([$filial->nome, $filial->fantasia] as $candidate) {
                if (! filled($candidate)) {
                    continue;
                }

                $normalized = $this->normalizeForMatch($candidate);
                if ($normalized === '' || ! str_contains($name, $normalized)) {
                    continue;
                }

                if (mb_strlen($normalized) > $bestLen) {
                    $best = $filial;
                    $bestLen = mb_strlen($normalized);
                }
            }
        }

        return $best;
    }

    private function normalizeForMatch(string $text): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim(Str::ascii($text))) ?? '');
    }

    private function normalizedCnpj(): ?string
    {
        $cnpj = preg_replace('/\D/', '', $this->cnpj ?? '');

        return strlen($cnpj) === 14 ? $cnpj : null;
    }

    private function cnpjMatches(?string $filialCnpj, string $normalized): bool
    {
        $candidate = preg_replace('/\D/', '', $filialCnpj ?? '');

        return strlen($candidate) === 14 && $candidate === $normalized;
    }

    protected string $auditableModule = 'filiais';
    protected string $auditableEventPrefix = 'filiais';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Filial {$this->name} criada",
            'updated' => "Filial {$this->name} atualizada",
            'deleted' => "Filial {$this->name} excluída",
            default => null,
        };
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user');
    }

    public function getCnpjFormattedAttribute(): ?string
    {
        $cnpj = preg_replace('/\D/', '', $this->cnpj ?? '');
        if (strlen($cnpj) !== 14) return $this->cnpj;
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }

    /** Apelido da empresa-mãe (cod_emp), via cadastro Senior/comercial. */
    public function empresaApelido(): ?string
    {
        if ($this->cod_emp) {
            return \App\Models\Comercial\Filial::apelidoEmpresa((int) $this->cod_emp);
        }

        return $this->resolveComercialFilial()?->apelido
            ?: $this->resolveComercialFilial()?->label;
    }

    /** @param iterable<Branch> $branches */
    public static function attachEmpresaApelido(iterable $branches): void
    {
        foreach ($branches as $branch) {
            $branch->setAttribute('empresa_apelido', $branch->empresaApelido());
        }
    }
}
