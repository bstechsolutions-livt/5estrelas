<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoArq extends Model
{
    protected $table = 'intranet_solicitacao_arq';

    protected $fillable = [
        'solicitacao_id',
        'arquivo_id',
        'usuario'
    ];

    public function solicitacao(){
        return $this->belongsTo(Solicitacao::class,'solicitacao_id','id');
    }

    public function file(){
        return $this->belongsTo(File::class,'arquivo_id','id');
    }

    public function usuario(){
        return $this->belongsTo(Funcionario::class,'usuario');
    }
}
