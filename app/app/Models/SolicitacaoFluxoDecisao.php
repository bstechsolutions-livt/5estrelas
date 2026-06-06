<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Decisão disponível numa etapa do fluxo.
 *
 * Exemplo: Na etapa "Aprovação Gestor":
 *   - "Aprovado" → avança para etapa "Contabilidade"
 *   - "Reprovado" → volta para etapa "RH Triagem"
 *
 * Se a etapa não tem decisões, ao concluir ela simplesmente
 * avança para a próxima etapa na ordem sequencial.
 */
class SolicitacaoFluxoDecisao extends Model
{
    protected $table = 'intranet_solicitacao_fluxo_decisoes';

    protected $fillable = [
        'etapa_fluxo_id',
        'label',
        'cor',
        'icone',
        'etapa_destino_id',
        'acao',
        'etapa_andamento_id',
        'abrir_solicitacao_assunto_id',
        'ordem',
    ];

    protected $casts = [
        'etapa_fluxo_id' => 'integer',
        'etapa_destino_id' => 'integer',
        'etapa_andamento_id' => 'integer',
        'abrir_solicitacao_assunto_id' => 'integer',
        'ordem' => 'integer',
    ];

    // ─── Relacionamentos ───────────────────────────────────────

    public function etapa()
    {
        return $this->belongsTo(SolicitacaoFluxoEtapa::class, 'etapa_fluxo_id');
    }

    public function etapaDestino()
    {
        return $this->belongsTo(SolicitacaoFluxoEtapa::class, 'etapa_destino_id');
    }

    public function abrirSolicitacaoAssunto()
    {
        return $this->belongsTo(SolicitacaoAssunto::class, 'abrir_solicitacao_assunto_id');
    }

    // ─── Helpers ───────────────────────────────────────────────

    /**
     * Se acao = 'finalizar', o fluxo encerra como concluido.
     */
    public function isFinalizacao(): bool
    {
        return $this->acao === 'finalizar';
    }

    /**
     * Se acao = 'resolver', o fluxo encerra e a solicitação é resolvida.
     */
    public function isResolucao(): bool
    {
        return $this->acao === 'resolver';
    }

    /**
     * Se acao = 'voltar_solicitante', a solicitação volta para o departamento do solicitante.
     */
    public function isVoltarSolicitante(): bool
    {
        return $this->acao === 'voltar_solicitante';
    }

    /**
     * Se acao = 'abrir_solicitacao', cria uma solicitação filha vinculada.
     */
    public function isAbrirSolicitacao(): bool
    {
        return $this->acao === 'abrir_solicitacao';
    }
}
