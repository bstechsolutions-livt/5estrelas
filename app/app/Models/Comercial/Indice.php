<?php

namespace App\Models\Comercial;

use Illuminate\Database\Eloquent\Model;

class Indice extends Model
{
    protected $table = 'bs_comercial_indices';

    protected $guarded = [];

    protected $casts = [
        'valor' => 'decimal:4',
    ];

    /** Retorna todos os índices como mapa chave => valor. */
    public static function mapa(): array
    {
        return static::pluck('valor', 'chave')->map(fn ($v) => (float) $v)->all();
    }
}
