<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Histórico/auditoria de cada transição no fluxo de uma solicitação.
 *
 * Registra de qual etapa veio, para qual foi, qual decisão foi tomada
 * e quem realizou a ação.
 */
class SolicitacaoFluxoHistorico extends Model
{
  protected $table = 'intranet_solicitacao_fluxo_historico';

  protected $fillable = [
    'solicitacao_id',
    'fluxo_id',
    'etapa_anterior_id',
    'etapa_nova_id',
    'decisao_id',
    'decisao_label',
    'usuario_alteracao',
    'observacao',
  ];

  protected $casts = [
    'solicitacao_id'    => 'integer',
    'fluxo_id'          => 'integer',
    'etapa_anterior_id' => 'integer',
    'etapa_nova_id'     => 'integer',
    'decisao_id'        => 'integer',
    'usuario_alteracao' => 'integer',
  ];

  // ─── Relacionamentos ───────────────────────────────────────

  public function solicitacao()
  {
    return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
  }

  public function fluxo()
  {
    return $this->belongsTo(SolicitacaoFluxo::class, 'fluxo_id');
  }

  public function etapaAnterior()
  {
    return $this->belongsTo(SolicitacaoFluxoEtapa::class, 'etapa_anterior_id');
  }

  public function etapaNova()
  {
    return $this->belongsTo(SolicitacaoFluxoEtapa::class, 'etapa_nova_id');
  }

  public function decisao()
  {
    return $this->belongsTo(SolicitacaoFluxoDecisao::class, 'decisao_id');
  }

  public function usuarioAlteracao()
  {
    return $this->belongsTo(Funcionario::class, 'usuario_alteracao');
  }
}
