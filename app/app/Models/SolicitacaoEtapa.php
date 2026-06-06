<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model para etapas configuráveis por assunto de solicitação.
 * 
 * Permite configurar etapas como "Triagem", "Entrevista RH", "Documentação", etc.
 * para acompanhamento detalhado do andamento das solicitações.
 */
class SolicitacaoEtapa extends Model
{
  protected $table = 'intranet_solicitacao_etapas';

  protected $fillable = [
    'assunto_id',
    'nome',
    'descricao',
    'cor',
    'icone',
    'ordem',
    'ativo',
  ];

  protected $casts = [
    'ordem' => 'integer',
    'assunto_id' => 'integer',
  ];

  /**
   * Assunto ao qual esta etapa pertence
   */
  public function assunto()
  {
    return $this->belongsTo(SolicitacaoAssunto::class, 'assunto_id');
  }

  /**
   * Solicitações que estão atualmente nesta etapa
   */
  public function solicitacoesAtuais()
  {
    return $this->hasMany(SolicitacaoEtapaAtual::class, 'etapa_id');
  }

  /**
   * Scope para etapas ativas
   */
  public function scopeAtivas($query)
  {
    return $query->where('ativo', 'S');
  }

  /**
   * Scope para ordenar por ordem
   */
  public function scopeOrdenadas($query)
  {
    return $query->orderBy('ordem');
  }
}
