<?php

namespace App\Models\Comercial;

use Illuminate\Database\Eloquent\Model;

class Escala extends Model
{
    protected $table = 'bs_comercial_escalas';

    protected $guarded = [];

    protected $casts = [
        'dias_mes' => 'decimal:2',
        'horas_mes' => 'decimal:2',
        'ativo' => 'boolean',
    ];
}
