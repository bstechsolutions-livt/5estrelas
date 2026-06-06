<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Etapa dentro de um fluxo de solicitação.
 *
 * Cada etapa pertence a um departamento específico.
 * Quando o departamento finaliza sua parte, a solicitação
 * avança automaticamente para a próxima etapa.
 *
 * Pode ter decisões configuradas (ex: "Aprovado?"/"Reprovado?")
 * que determinam para qual etapa a solicitação será redirecionada.
 */
class SolicitacaoFluxoEtapa extends Model
{
    protected $table = 'intranet_solicitacao_fluxo_etapas';

    protected $fillable = [
        'fluxo_id',
        'nome',
        'descricao',
        'departamento',
        'assunto_id',
        'etapa_andamento_id',
        'manter_responsavel',
        'responsavel_padrao',
        'permitir_responsavel_externo',
        'permitir_solicitante_avancar',
        'exibir_campos_assunto',
        'prazo_horas',
        'instrucoes',
        'cor',
        'icone',
        'ordem',
        'ativo',
        'tipo',
    ];

    protected $casts = [
        'fluxo_id' => 'integer',
        'assunto_id' => 'integer',
        'etapa_andamento_id' => 'integer',
        'responsavel_padrao' => 'integer',
        'prazo_horas' => 'integer',
        'permitir_responsavel_externo' => 'string',
        'permitir_solicitante_avancar' => 'string',
        'exibir_campos_assunto' => 'string',
        'ordem' => 'integer',
    ];

    // ─── Relacionamentos ───────────────────────────────────────

    public function fluxo()
    {
        return $this->belongsTo(SolicitacaoFluxo::class, 'fluxo_id');
    }

    public function assunto()
    {
        return $this->belongsTo(SolicitacaoAssunto::class, 'assunto_id');
    }

    public function etapaAndamento()
    {
        return $this->belongsTo(SolicitacaoEtapa::class, 'etapa_andamento_id');
    }

    public function responsavelPadrao()
    {
        return $this->belongsTo(Funcionario::class, 'responsavel_padrao');
    }

    public function decisoes()
    {
        return $this->hasMany(SolicitacaoFluxoDecisao::class, 'etapa_fluxo_id')->orderBy('ordem');
    }

    public function campos()
    {
        return $this->hasMany(SolicitacaoFluxoEtapaCampo::class, 'etapa_fluxo_id')->orderBy('ordem');
    }

    public function responsaveisPermitidos()
    {
        return $this->hasMany(SolicitacaoFluxoEtapaResponsavel::class, 'etapa_fluxo_id');
    }

    public function execucoesAtuais()
    {
        return $this->hasMany(SolicitacaoFluxoExecucao::class, 'etapa_atual_id');
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function scopeAtivas($query)
    {
        return $query->where('ativo', 'S');
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('ordem');
    }

    // ─── Helpers ───────────────────────────────────────────────

    /**
     * Verifica se esta etapa tem decisões configuradas.
     * Se true, o responsável precisa escolher uma opção para avançar.
     * Se false, ao concluir vai direto para a próxima etapa na ordem.
     */
    public function temDecisoes(): bool
    {
        return $this->decisoes()->exists();
    }

    /**
     * Retorna a próxima etapa ativa na ordem do fluxo (sem considerar decisões).
     */
    public function proximaEtapa()
    {
        return SolicitacaoFluxoEtapa::where('fluxo_id', $this->fluxo_id)
            ->where('ativo', 'S')
            ->where('ordem', '>', $this->ordem)
            ->orderBy('ordem')
            ->first();
    }

    /**
     * Retorna a etapa anterior ativa na ordem do fluxo.
     */
    public function etapaAnterior()
    {
        return SolicitacaoFluxoEtapa::where('fluxo_id', $this->fluxo_id)
            ->where('ativo', 'S')
            ->where('ordem', '<', $this->ordem)
            ->orderByDesc('ordem')
            ->first();
    }

    /**
     * Verifica se é a última etapa ativa do fluxo.
     */
    public function isUltimaEtapa(): bool
    {
        return $this->proximaEtapa() === null;
    }

    /**
     * Verifica se a etapa está em Modo Exclusivo do Solicitante.
     * Quando true, apenas o solicitante da solicitação pode avançar a etapa;
     * o responsável do departamento não vê campos, decisões nem botões de avanço.
     */
    public function isModoExclusivo(): bool
    {
        return $this->permitir_solicitante_avancar === 'E';
    }

    /**
     * Verifica se o solicitante pode agir nesta etapa.
     * Retorna true quando o campo está em 'S' (responsável e solicitante podem avançar)
     * ou em 'E' (apenas o solicitante pode avançar).
     */
    public function solicitantePodeAgir(): bool
    {
        return in_array($this->permitir_solicitante_avancar, ['S', 'E'], true);
    }
}
