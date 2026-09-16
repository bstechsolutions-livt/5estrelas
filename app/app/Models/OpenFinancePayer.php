<?php

namespace App\Models;

use App\Models\Comercial\Filial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado da integração Open Finance (TecnoSpeed) por CNPJ/pagador.
 *
 * Dados cadastrais (razão social, endereço) permanecem em bs_comercial_filiais / branches.
 * Esta tabela guarda só o estado remoto da TecnoSpeed — sem tokens nem payload bruto.
 */
class OpenFinancePayer extends Model
{
    public const PROVIDER_TECNOSPEED = 'tecnospeed';

    protected $table = 'open_finance_payers';

    protected $fillable = [
        'comercial_filial_id',
        'branch_id',
        'cpf_cnpj',
        'name',
        'provider',
        'statement_activated',
        'remote_exists',
        'last_status',
        'last_checked_at',
    ];

    protected $casts = [
        'statement_activated' => 'boolean',
        'remote_exists' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function comercialFilial(): BelongsTo
    {
        return $this->belongsTo(Filial::class, 'comercial_filial_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
