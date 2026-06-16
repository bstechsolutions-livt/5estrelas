<?php

namespace App\Models\Comercial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comercial — Saúde Contratual: metas configuráveis por cliente.
 */
class SaudeMeta extends Model
{
    protected $table = 'bs_comercial_saude_metas';

    protected $guarded = [];

    protected $casts = [
        'margem_minima' => 'decimal:2',
        'margem_alvo' => 'decimal:2',
        'max_folha_pct' => 'decimal:2',
        'inadimplencia_max' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
