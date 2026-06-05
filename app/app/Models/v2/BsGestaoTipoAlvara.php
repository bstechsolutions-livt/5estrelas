<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;

class BsGestaoTipoAlvara extends Model
{
    protected $table = 'bs_gestao_tipos_alvara';

    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'dias_alerta_1',
        'dias_alerta_2',
        'dias_alerta_3',
        'dias_alerta_4',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'dias_alerta_1' => 'integer',
        'dias_alerta_2' => 'integer',
        'dias_alerta_3' => 'integer',
        'dias_alerta_4' => 'integer',
    ];

    public function alvaras()
    {
        return $this->hasMany(BsGestaoAlvara::class, 'tipo_alvara_id');
    }
}
