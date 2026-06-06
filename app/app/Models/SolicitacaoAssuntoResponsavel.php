<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model para Responsáveis de Assunto em Solicitações
 * 
 * #22263 - Permissão por Assunto em Solicitações
 * Define quais usuários podem VER e ATENDER solicitações de um determinado assunto.
 * Se um assunto não tiver responsáveis configurados, todos do departamento podem ver/atender.
 */
class SolicitacaoAssuntoResponsavel extends Model
{
  protected $table = 'solicitacao_assunto_responsaveis';

  protected $fillable = [
    'assunto_id',
    'matricula',
  ];

  protected $casts = [
    'assunto_id' => 'integer',
    'matricula' => 'integer',
  ];

  /**
   * Relacionamento com o Assunto
   */
  public function assunto()
  {
    return $this->belongsTo(SolicitacaoAssunto::class, 'assunto_id');
  }

  /**
   * Relacionamento com o Funcionário
   */
  public function funcionario()
  {
    return $this->belongsTo(Funcionario::class, 'matricula');
  }
}
