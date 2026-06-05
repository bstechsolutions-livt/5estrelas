<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;

class GestaoContratoMedicaoAnexo extends Model
{
  protected $table = 'gestao_contratos_medicoes_anexos';

  protected $fillable = [
    'medicao_id',
    'tipo',
    'nome_arquivo',
    'caminho',
    'tamanho',
    'mime_type',
    'descricao',
    'created_by',
  ];

  // ═══════════════════════════════════════════════════════════
  //                      RELATIONSHIPS
  // ═══════════════════════════════════════════════════════════

  public function medicao()
  {
    return $this->belongsTo(GestaoContratoMedicao::class, 'medicao_id');
  }
}
