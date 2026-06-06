<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model para registrar a etapa atual de uma solicitação.
 * 
 * Cada solicitação pode ter apenas uma etapa atual (unique constraint).
 */
class SolicitacaoEtapaAtual extends Model
{
  protected $table = 'intranet_solicitacao_etapa_atual';

  protected $fillable = [
    'solicitacao_id',
    'etapa_id',
    'usuario_alteracao',
    'data_alteracao',
  ];

  protected $casts = [
    'solicitacao_id' => 'integer',
    'etapa_id' => 'integer',
    'usuario_alteracao' => 'integer',
    'data_alteracao' => 'datetime',
  ];

  /**
   * Solicitação relacionada
   */
  public function solicitacao()
  {
    return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
  }

  /**
   * Etapa atual
   */
  public function etapa()
  {
    return $this->belongsTo(SolicitacaoEtapa::class, 'etapa_id');
  }

  /**
   * Usuário que fez a última alteração
   */
  public function usuarioAlteracao()
  {
    return $this->belongsTo(Funcionario::class, 'usuario_alteracao');
  }
}
