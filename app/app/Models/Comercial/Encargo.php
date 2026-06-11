<?php

namespace App\Models\Comercial;

use Illuminate\Database\Eloquent\Model;

class Encargo extends Model
{
    protected $table = 'bs_comercial_encargos';

    protected $guarded = [];

    protected $casts = [
        'percentual' => 'decimal:4',
        'ordem' => 'integer',
    ];

    /** Total geral (somatório) dos encargos — percentual aplicado na composição. */
    public static function totalGeral(): float
    {
        return (float) static::sum('percentual');
    }
}
