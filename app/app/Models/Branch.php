<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    use Auditable;

    protected $fillable = ['name', 'cnpj', 'code', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['display_name'];

    /** Nome curto para exibição (apelido da filial espelhada na Senior, se houver). */
    public function getDisplayNameAttribute(): string
    {
        return $this->resolveDisplayName();
    }

    /** Apelido da filial comercial vinculada a este cadastro local. */
    public function resolveDisplayName(): string
    {
        $filial = $this->resolveComercialFilial();
        if ($filial !== null) {
            return filled($filial->apelido) ? $filial->apelido : $filial->label;
        }

        return \App\Models\Comercial\Filial::gerarApelido($this->name);
    }

    /** Filial Senior espelhada (match por CNPJ, cod_fil ou cod_emp único). */
    public function resolveComercialFilial(): ?\App\Models\Comercial\Filial
    {
        $query = \App\Models\Comercial\Filial::query()->where('ativo', true);
        $cnpj = $this->normalizedCnpj();

        if ($cnpj !== null) {
            $byCnpj = (clone $query)->get()->first(
                fn (\App\Models\Comercial\Filial $f) => $this->cnpjMatches($f->cnpj, $cnpj),
            );
            if ($byCnpj !== null) {
                return $byCnpj;
            }
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

        $byCodEmp = (clone $query)->where('cod_emp', $code)->get();
        if ($byCodEmp->count() === 1) {
            return $byCodEmp->first();
        }

        return null;
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
}
