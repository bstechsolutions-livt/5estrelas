<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model para histórico de mudanças de etapa de uma solicitação.
 * 
 * Registra todas as alterações de etapa para auditoria e timeline.
 */
class SolicitacaoEtapaHistorico extends Model
{
  protected $table = 'intranet_solicitacao_etapa_historico';

  protected $fillable = [
    'solicitacao_id',
    'etapa_anterior_id',
    'etapa_nova_id',
    'usuario_alteracao',
    'observacao',
  ];

  protected $casts = [
    'solicitacao_id' => 'integer',
    'etapa_anterior_id' => 'integer',
    'etapa_nova_id' => 'integer',
    'usuario_alteracao' => 'integer',
  ];

  /**
   * Solicitação relacionada
   */
  public function solicitacao()
  {
    return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
  }

  /**
   * Etapa anterior
   */
  public function etapaAnterior()
  {
    return $this->belongsTo(SolicitacaoEtapa::class, 'etapa_anterior_id');
  }

  /**
   * Nova etapa
   */
  public function etapaNova()
  {
    return $this->belongsTo(SolicitacaoEtapa::class, 'etapa_nova_id');
  }

  /**
   * Usuário que fez a alteração
   */
  public function usuarioAlteracao()
  {
    return $this->belongsTo(Funcionario::class, 'usuario_alteracao');
  }
}
