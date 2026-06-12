<?php

namespace App\Models\Comercial;

use Illuminate\Database\Eloquent\Model;

class Insumo extends Model
{
    protected $table = 'bs_comercial_insumos';

    protected $guarded = [];

    protected $casts = [
        'valor' => 'decimal:2',
        'ordem' => 'integer',
    ];
}
