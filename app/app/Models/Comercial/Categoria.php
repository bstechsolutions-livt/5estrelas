<?php

namespace App\Models\Comercial;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'bs_comercial_categorias';

    protected $guarded = [];

    protected $casts = [
        'salario_base' => 'decimal:2',
        'periculosidade_pct' => 'decimal:2',
        'intrajornada_h' => 'decimal:2',
        'desconto_vt_pct' => 'decimal:2',
        'va' => 'decimal:2',
        'vt' => 'decimal:2',
        'plano_saude' => 'decimal:2',
        'fundo_social' => 'decimal:2',
        'sst' => 'decimal:2',
        'cna' => 'decimal:2',
        'seguro_vida' => 'decimal:2',
        'uniforme' => 'decimal:2',
        'reciclagem' => 'decimal:2',
        'gta' => 'decimal:2',
        'cofre' => 'decimal:2',
        'arma' => 'decimal:2',
        'colete' => 'decimal:2',
        'tem_arma' => 'boolean',
        'tem_moto' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function cct()
    {
        return $this->belongsTo(Cct::class, 'cct_id');
    }
}
