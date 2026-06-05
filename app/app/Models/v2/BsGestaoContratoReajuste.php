<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;

class BsGestaoContratoReajuste extends Model
{
    protected $table = 'bs_gestao_contratos_reajustes';

    protected $fillable = [
        'contrato_id',
        'data_reajuste',
        'valor_anterior',
        'valor_reajustado',
        'valor_proposto',
        'percentual_aplicado',
        'indice_utilizado',
        'valor_indice',
        'mes_base_indice',
        'valor_negociado',
        'reducao_obtida',
        'negociador',
        'observacoes',
        'created_by',
    ];

    protected $casts = [
        'data_reajuste' => 'date',
        'valor_anterior' => 'decimal:2',
        'valor_reajustado' => 'decimal:2',
        'valor_proposto' => 'decimal:2',
        'percentual_aplicado' => 'decimal:4',
        'valor_indice' => 'decimal:4',
        'valor_negociado' => 'decimal:2',
        'reducao_obtida' => 'decimal:2',
    ];

    public function contrato()
    {
        return $this->belongsTo(BsGestaoContrato::class, 'contrato_id');
    }
}
