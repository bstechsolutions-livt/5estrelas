<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoAgendamento extends Model
{
    // use HasFactory;

    protected $table = 'intranet_solicitacao_agend';

    // Tipos de agendamento
    const TIPO_VISITA = 'visita';
    const TIPO_LEMBRETE = 'lembrete';

    protected $fillable = [
        'mat_responsavel',
        'rota',
        'data_agendamento',
        'filial',
        'user_cria',
        'data_fim_agendamento',
        'tipo_finalizacao',
        'data_cancelamento',
        'mat_cancelamento',
        'mat_termino',
        'data_termino',
        'inicio_atendimento',
        'mat_inicio_atendimento',
        'status',
        'id_arquivo_assinatura',
        'observacao',
        'tipo'
    ];


    public function solicitacoes()
    {
        return $this->belongsToMany(
            Solicitacao::class,  // Model do agendamento
            'intranet_solicitacao_ag_sol',  // Tabela pivô
            'agendamento_id',               // FK referente à solicitação
            'solicitacao_id'                // FK referente a agendamento
        )->where('departamento_responsavel', session('auth')->areaatuacao);
    }
}
