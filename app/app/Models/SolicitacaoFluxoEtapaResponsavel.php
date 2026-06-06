<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoFluxoEtapaResponsavel extends Model
{
  protected $table = 'intranet_sol_fluxo_etapa_responsaveis';

  protected $fillable = [
    'etapa_fluxo_id',
    'matricula',
  ];

  protected $casts = [
    'etapa_fluxo_id' => 'integer',
    'matricula' => 'integer',
  ];

  public function etapa()
  {
    return $this->belongsTo(SolicitacaoFluxoEtapa::class, 'etapa_fluxo_id');
  }

  public function funcionario()
  {
    return $this->belongsTo(Funcionario::class, 'matricula');
  }
}
