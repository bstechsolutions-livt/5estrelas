<?php

namespace App\Models\Comercial;

use Illuminate\Database\Eloquent\Model;

class Cct extends Model
{
    protected $table = 'bs_comercial_ccts';

    protected $guarded = [];

    protected $casts = [
        'data_base' => 'date',
        'vigencia_inicio' => 'date',
        'vigencia_fim' => 'date',
        'ativo' => 'boolean',
    ];

    public function categorias()
    {
        return $this->hasMany(Categoria::class, 'cct_id');
    }
}
