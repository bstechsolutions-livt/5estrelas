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

    /** Nome curto para exibição (apelido da empresa espelhada na Senior, se houver). */
    public function getDisplayNameAttribute(): string
    {
        return $this->resolveDisplayName();
    }

    public function resolveDisplayName(): string
    {
        if (is_numeric($this->code)) {
            $apelido = \App\Models\Comercial\Filial::where('cod_emp', (int) $this->code)->value('apelido');
            if (filled($apelido)) {
                if (preg_match('/FILIAL\s+([A-ZÀ-Ú]{2,})\b/iu', $this->name, $m)) {
                    return trim($apelido . ' ' . mb_strtoupper($m[1]));
                }

                return $apelido;
            }
        }

        return \App\Models\Comercial\Filial::gerarApelido($this->name);
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
