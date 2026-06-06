<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoComArq extends Model
{
    protected $table = 'intranet_solicitacao_com_arq';

    protected $fillable = [
        'solicitacao_id',
        'comentario_id',
        'arquivo_id',
        'usuario'
    ];

    public function solicitacao(){
        return $this->belongsTo(Solicitacao::class,'solicitacao_id','id');
    }

    public function file(){
        return $this->belongsTo(File::class,'arquivo_id','id');
    }

    public function comentario(){
        return $this->belongsTo(SolicitacaoCom::class,'comentario_id','id');
    }

    public function usuario(){
        return $this->belongsTo(Funcionario::class,'usuario');
    }
}
