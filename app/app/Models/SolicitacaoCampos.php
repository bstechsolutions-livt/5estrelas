<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoCampos extends Model
{
    protected $table = 'intranet_solicitacao_campos';

    protected $fillable = [
        'descricao',
        'assunto_id',
        'observacao',
        'obrigatorio',
        'tipo',           // 'texto' ou 'selecao'
        'opcoes_titulo',  // JSON array com opções quando tipo = 'selecao'
    ];

    protected $casts = [
        'opcoes_titulo' => 'array',
    ];
}
