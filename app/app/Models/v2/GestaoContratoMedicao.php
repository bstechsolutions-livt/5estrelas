<?php

namespace App\Models\v2;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class GestaoContratoMedicao extends Model
{
  protected $table = 'gestao_contratos_medicoes';

  protected $fillable = [
    'contrato_id',
    'competencia',
    'valor_previsto',
    'valor_real',
    'numero_nf',
    'numero_boleto',
    'data_vencimento',
    'data_envio',
    'data_envio_nf',
    'data_pagamento',
    'etapa',
    'alerta_divergencia',
    'percentual_variacao',
    'observacoes',
    'created_by',
    'updated_by',
    // Rastreabilidade
    'mat_entrada_nota',
    'data_entrada_nota',
    'mat_financeiro',
    'data_financeiro',
    'data_finalizacao',
    'mat_gestor',
  ];

  protected $casts = [
    'data_vencimento' => 'date',
    'data_envio' => 'date',
    'data_envio_nf' => 'date',
    'data_pagamento' => 'date',
    'data_entrada_nota' => 'date',
    'data_financeiro' => 'date',
    'data_finalizacao' => 'date',
    'valor_previsto' => 'decimal:2',
    'valor_real' => 'decimal:2',
    'percentual_variacao' => 'decimal:2',
    'alerta_divergencia' => 'boolean',
  ];

  protected $appends = ['competencia_formatada', 'status_label'];

  // ═══════════════════════════════════════════════════════════
  //                      RELATIONSHIPS
  // ═══════════════════════════════════════════════════════════

  public function contrato()
  {
    return $this->belongsTo(BsGestaoContrato::class, 'contrato_id');
  }

  public function anexos()
  {
    return $this->hasMany(GestaoContratoMedicaoAnexo::class, 'medicao_id');
  }

  // ═══════════════════════════════════════════════════════════
  //                       ACCESSORS
  // ═══════════════════════════════════════════════════════════

  public function getCompetenciaFormatadaAttribute()
  {
    if (!$this->competencia) return null;

    $meses = [
      '01' => 'Janeiro',
      '02' => 'Fevereiro',
      '03' => 'Março',
      '04' => 'Abril',
      '05' => 'Maio',
      '06' => 'Junho',
      '07' => 'Julho',
      '08' => 'Agosto',
      '09' => 'Setembro',
      '10' => 'Outubro',
      '11' => 'Novembro',
      '12' => 'Dezembro',
    ];

    $partes = explode('-', $this->competencia);
    $mes = $meses[$partes[1]] ?? $partes[1];

    return "{$mes}/{$partes[0]}";
  }

  public function getStatusLabelAttribute()
  {
    $labels = [
      'PENDENTE' => 'Pendente Envio',
      'ENVIADA' => 'NF/Boleto Enviado',
      'ENTRADA_NOTA' => 'Em Entrada de Nota',
      'FINANCEIRO' => 'No Financeiro',
      'PAGO' => 'Pago',
    ];

    return $labels[$this->etapa] ?? $this->etapa;
  }

  // ═══════════════════════════════════════════════════════════
  //                        SCOPES
  // ═══════════════════════════════════════════════════════════

  public function scopePendentes($query)
  {
    return $query->where('etapa', 'PENDENTE');
  }

  public function scopeEnviadas($query)
  {
    return $query->where('etapa', 'ENVIADA');
  }

  public function scopeEntradaNota($query)
  {
    return $query->where('etapa', 'ENTRADA_NOTA');
  }

  public function scopeFinanceiro($query)
  {
    return $query->where('etapa', 'FINANCEIRO');
  }

  public function scopePagas($query)
  {
    return $query->where('etapa', 'PAGO');
  }

  public function scopeComAlerta($query)
  {
    return $query->where('alerta_divergencia', true);
  }

  public function scopeDoMes($query, $competencia = null)
  {
    $competencia = $competencia ?? Carbon::now()->format('Y-m');
    return $query->where('competencia', $competencia);
  }

    // ═══════════════════════════════════════════════════════════
    //                        METHODS
    // ═══════════════════════════════════════════════════════════

  /**
   * Calcula o percentual de variação e define se gera alerta
   */
  public function calcularDivergencia(): void
  {
    if (!$this->valor_previsto || $this->valor_previsto == 0) {
      $this->percentual_variacao = 0;
      $this->alerta_divergencia = false;
      return;
    }

    $this->percentual_variacao = round(
      (($this->valor_real - $this->valor_previsto) / $this->valor_previsto) * 100,
      2
    );

    // Alerta se valor real excede valor previsto (ponto 7 do plano)
    $this->alerta_divergencia = $this->valor_real > $this->valor_previsto;
  }
}
