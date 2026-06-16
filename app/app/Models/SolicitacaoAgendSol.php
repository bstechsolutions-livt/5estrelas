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

    /**
     * Agendamento vinculado (usado, p.ex., por cancelarLembrete para checar
     * se há outros agendamentos ativos da mesma solicitação).
     */
    public function agendamento()
    {
        return $this->belongsTo(SolicitacaoAgendamento::class, 'agendamento_id', 'id');
    }

    /**
     * Solicitação vinculada.
     */
    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id', 'id');
    }
}
