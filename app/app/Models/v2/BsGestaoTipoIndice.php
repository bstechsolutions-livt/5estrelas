<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;

class BsGestaoTipoIndice extends Model
{
    protected $table = 'bs_gestao_tipos_indice';

    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function contratos()
    {
        return $this->hasMany(BsGestaoContrato::class, 'tipo_indice_id');
    }
}
