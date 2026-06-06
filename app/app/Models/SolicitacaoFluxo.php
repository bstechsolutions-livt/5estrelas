<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fluxo/Workflow de solicitações vinculado a um assunto.
 *
 * Define a cadeia de departamentos por onde a solicitação passa automaticamente.
 * Ex: RH → Gestão → Contabilidade → RH
 */
class SolicitacaoFluxo extends Model
{
  protected $table = 'intranet_solicitacao_fluxos';

  protected $fillable = [
    'assunto_id',
    'nome',
    'descricao',
    'ativo',
    'versao',
  ];

  protected $casts = [
    'assunto_id' => 'integer',
    'versao'     => 'integer',
  ];

  // ─── Relacionamentos ───────────────────────────────────────

  public function assunto()
  {
    return $this->belongsTo(SolicitacaoAssunto::class, 'assunto_id');
  }

  public function etapas()
  {
    return $this->hasMany(SolicitacaoFluxoEtapa::class, 'fluxo_id')->orderBy('ordem');
  }

  public function etapasAtivas()
  {
    return $this->hasMany(SolicitacaoFluxoEtapa::class, 'fluxo_id')
      ->where('ativo', 'S')
      ->orderBy('ordem');
  }

  public function execucoes()
  {
    return $this->hasMany(SolicitacaoFluxoExecucao::class, 'fluxo_id');
  }

  // ─── Scopes ────────────────────────────────────────────────

  public function scopeAtivos($query)
  {
    return $query->where('ativo', 'S');
  }

    // ─── Helpers ───────────────────────────────────────────────

  /**
   * Retorna a primeira etapa ativa do fluxo (ponto de entrada).
   */
  public function primeiraEtapa()
  {
    return $this->etapasAtivas()->first();
  }

  /**
   * Verifica se este fluxo tem execuções ativas (em_andamento ou aguardando_decisao).
   * Usado para decidir se é preciso versionar ao editar.
   */
  public function temExecucoesAtivas(): bool
  {
    return $this->execucoes()
      ->whereIn('status', ['em_andamento', 'aguardando_decisao', 'aguardando_solicitante'])
      ->exists();
  }

  /**
   * Conta quantas execuções ativas este fluxo possui.
   */
  public function qtdExecucoesAtivas(): int
  {
    return $this->execucoes()
      ->whereIn('status', ['em_andamento', 'aguardando_decisao', 'aguardando_solicitante'])
      ->count();
  }
}
