<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;

class GestaoContratoRenovacao extends Model
{
  protected $table = 'gestao_contratos_renovacoes';

  protected $fillable = [
    'contrato_id',
    'data_renovacao',
    'nova_data_inicio',
    'nova_data_fim',
    'valor_anterior',
    'valor_novo',
    'percentual_variacao',
    'percentual_divergencia_limite',
    'dentro_divergencia',
    'id_solicitacao_compras_nova',
    'status',
    'observacoes',
    'created_by',
    'updated_by',
  ];

  protected $casts = [
    'data_renovacao' => 'date',
    'nova_data_inicio' => 'date',
    'nova_data_fim' => 'date',
    'valor_anterior' => 'decimal:2',
    'valor_novo' => 'decimal:2',
    'percentual_variacao' => 'decimal:2',
    'percentual_divergencia_limite' => 'decimal:2',
    'dentro_divergencia' => 'boolean',
  ];

  // ═══════════════════════════════════════════════════════════
  //                      RELATIONSHIPS
  // ═══════════════════════════════════════════════════════════

  public function contrato()
  {
    return $this->belongsTo(BsGestaoContrato::class, 'contrato_id');
  }

  // ═══════════════════════════════════════════════════════════
  //                        SCOPES
  // ═══════════════════════════════════════════════════════════

  public function scopeAprovadas($query)
  {
    return $query->where('status', 'APROVADA');
  }

  public function scopePendentes($query)
  {
    return $query->where('status', 'PENDENTE_COMPRAS');
  }

  public function scopeDentroDivergencia($query)
  {
    return $query->where('dentro_divergencia', true);
  }

  public function scopeForaDivergencia($query)
  {
    return $query->where('dentro_divergencia', false);
  }

    // ═══════════════════════════════════════════════════════════
    //                        METHODS
    // ═══════════════════════════════════════════════════════════

  /**
   * Verifica se a renovação está dentro da divergência permitida.
   * Regra: valor_novo <= valor_anterior * (1 + percentual_divergencia / 100)
   */
  public static function verificarDivergencia(float $valorAnterior, float $valorNovo, float $percentualLimite): array
  {
    if ($valorAnterior == 0) {
      return [
        'dentro' => true,
        'percentual' => 0,
      ];
    }

    $percentualVariacao = round((($valorNovo - $valorAnterior) / $valorAnterior) * 100, 2);
    $limiteMaximo = $valorAnterior * (1 + $percentualLimite / 100);

    return [
      'dentro' => $valorNovo <= $limiteMaximo,
      'percentual' => $percentualVariacao,
      'limite_valor' => round($limiteMaximo, 2),
    ];
  }
}
