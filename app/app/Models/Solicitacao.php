<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Solicitacao extends Model
{
    protected $table = 'intranet_solicitacao';

    protected $fillable = [
        'titulo',
        'descricao',
        'status',
        'prioridade',
        'usuario_solicitante',
        'usuario_responsavel',
        'departamento_responsavel',
        'filial_id',
        'assunto_id',
        'usuario_origem',
        'previsao_entrega',
        'solicitacao_pai_id',
        'hash_duplicata',
        'data_conclusao',
    ];

    protected $appends = [
        'dias_aberto'
    ];

    protected $casts = [
        'previsao_entrega' => 'datetime',
        'data_conclusao' => 'datetime',
    ];

    public function getDiasAbertoAttribute()
    {
        return Carbon::parse($this->created_at)->diffInDays(Carbon::now());
    }

    public function movimentacoes()
    {
        return $this->hasMany(SolicitacaoMov::class, 'solicitacao_id', 'id')->orderBy('id', 'desc');
    }

    public function comentarios()
    {
        return $this->hasMany(SolicitacaoCom::class, 'solicitacao_id', 'id')->orderBy('id');
    }

    public function arquivos()
    {
        return $this->hasMany(SolicitacaoArq::class, 'solicitacao_id', 'id');
    }

    public function usuarioSolicitante()
    {
        return $this->belongsTo(Funcionario::class, 'usuario_solicitante')
            ->select(['id', 'matricula', 'nome', 'email', 'areaatuacao', 'fone']);
    }

    public function usuarioResponsavel()
    {
        return $this->belongsTo(Funcionario::class, 'usuario_responsavel')
            ->select(['id', 'matricula', 'nome', 'email', 'areaatuacao', 'fone']);
    }

    public function filial()
    {
        return $this->belongsTo(Filial::class, 'filial_id');
    }

    public function assunto()
    {
        return $this->belongsTo(SolicitacaoAssunto::class, 'assunto_id', 'id');
    }

    public function rotinas()
    {
        return $this->hasMany(SolicitacaoCRot::class, 'solicitacao_id', 'id');
    }

    public function responsaveisRelacionados()
    {
        return $this->hasMany(Funcionario::class, 'areaatuacao', 'departamento_responsavel')
            ->where('situacao', 'A')
            ->whereNotIn('matricula', [99999999, 7801, 10000]) // excluir usuarios ficiticios
            ->select('matricula', 'nome', 'areaatuacao');
    }

    public function agendamentos()
    {
        return $this->belongsToMany(
            SolicitacaoAgendamento::class,  // Model do agendamento
            'intranet_solicitacao_ag_sol',  // Tabela pivô
            'solicitacao_id',               // FK referente à solicitação
            'agendamento_id'                // FK referente a agendamento
        )->whereRaw("intranet_solicitacao_agend.status <> 'cancelado' ")->orderBy('intranet_solicitacao_agend.id', 'desc');
    }

    public function aprovacoes()
    {
        return $this->hasMany(SolicitacaoAprovacao::class, 'solicitacao_id', 'id')->orderBy('id', 'desc');
    }

    public function respostasSelecao()
    {
        return $this->hasMany(SolicitacaoSelecaoResposta::class, 'solicitacao_id', 'id')->orderBy('id', 'asc');
    }

    public function filialDeptoSelect()
    {
        return $this->hasOne(BsFilialDeptoSelect::class, 'solicitacao_id', 'id');
    }

    /**
     * Etapa atual da solicitação
     */
    public function etapaAtual()
    {
        return $this->hasOne(SolicitacaoEtapaAtual::class, 'solicitacao_id');
    }

    /**
     * Histórico de etapas da solicitação
     */
    public function etapaHistorico()
    {
        return $this->hasMany(SolicitacaoEtapaHistorico::class, 'solicitacao_id')->orderBy('created_at', 'desc');
    }

    // ─── Workflow/Fluxo ────────────────────────────────────────────

    /**
     * Execução atual do fluxo (em qual etapa do workflow a solicitação está)
     */
    public function fluxoExecucao()
    {
        return $this->hasOne(SolicitacaoFluxoExecucao::class, 'solicitacao_id');
    }

    /**
     * Histórico de transições no fluxo
     */
    public function fluxoHistorico()
    {
        return $this->hasMany(SolicitacaoFluxoHistorico::class, 'solicitacao_id')->orderBy('created_at', 'desc');
    }
}
