<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;

class BsGestaoEquipamentoTratativa extends Model
{

    protected $table = 'bs_gestao_equipamento_tratativas';

    protected $fillable = [
        'equipamento_id',
        'descricao',
        'data_registro',
        'created_by',
        'created_by_nome',
    ];

    protected $casts = [
        'data_registro' => 'date',
    ];

    // Relationships
    public function equipamento()
    {
        return $this->belongsTo(BsGestaoEquipamento::class, 'equipamento_id');
    }
}
