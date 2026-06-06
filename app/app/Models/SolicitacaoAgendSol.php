<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoAgendSol extends Model
{
    protected $table = 'intranet_solicitacao_ag_sol';

    public $timestamps = false; 

    protected $fillable = [
        'solicitacao_id',
        'agendamento_id'
    ];
}
