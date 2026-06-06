<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoMov extends Model
{
    protected $table = 'intranet_solicitacao_mov';

    protected $fillable = [
        'solicitacao_id',
        'usuario_origem',
        'usuario_destino',
        'tipo_movimentacao',
        'descricao',
        'usuario_movimentacao',
        'dados_extras'
    ];

    protected $casts = [
        'dados_extras' => 'array'
    ];

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id', 'id');
    }

    public function usuarioOrigem()
    {
        return $this->belongsTo(Funcionario::class, 'usuario_origem');
    }

    public function usuarioDestino()
    {
        return $this->belongsTo(Funcionario::class, 'usuario_destino');
    }

    public function usuarioMovimentacao()
    {
        return $this->belongsTo(Funcionario::class, 'usuario_movimentacao');
    }
}
