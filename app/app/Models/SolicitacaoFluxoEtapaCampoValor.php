<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Valor preenchido de um campo de etapa durante a execução do fluxo.
 *
 * Cada registro representa a resposta de um responsável para um campo
 * específico em uma execução do workflow.
 */
class SolicitacaoFluxoEtapaCampoValor extends Model
{
  protected $table = 'intranet_sol_fluxo_campo_valores';

  protected $fillable = [
    'execucao_id',
    'etapa_campo_id',
    'valor',
    'usuario_preenchimento',
  ];

  protected $casts = [
    'execucao_id'            => 'integer',
    'etapa_campo_id'         => 'integer',
    'usuario_preenchimento'  => 'integer',
  ];

  // ─── Relacionamentos ───────────────────────────────────────

  /**
   * Execução do fluxo a qual este valor pertence.
   */
  public function execucao()
  {
    return $this->belongsTo(SolicitacaoFluxoExecucao::class, 'execucao_id');
  }

  /**
   * Campo ao qual este valor se refere.
   */
  public function campo()
  {
    return $this->belongsTo(SolicitacaoFluxoEtapaCampo::class, 'etapa_campo_id');
  }

  /**
   * Usuário que preencheu o campo.
   */
  public function usuario()
  {
    return $this->belongsTo(Funcionario::class, 'usuario_preenchimento');
  }
}
