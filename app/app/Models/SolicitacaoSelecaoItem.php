<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoSelecaoItem extends Model
{
    protected $table = 'intranet_solicitacao_s_itens';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'selecao_id',
        'valor',
        'created_at',
        'updated_at',
    ];

    // Relação: um item pertence a uma seleção
    public function selecao()
    {
        return $this->belongsTo(SolicitacaoSelecao::class, 'selecao_id');
    }

    // Relação: um item pode ter várias respostas
    public function respostas()
    {
        return $this->hasMany(SolicitacaoSelecaoResposta::class, 'itens_id');
    }
}

