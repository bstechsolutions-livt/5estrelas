<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Campo configurado numa etapa do fluxo de workflow.
 *
 * Exemplo: Na etapa "Aprovação Gestor":
 *   - "Justificativa" (textarea, obrigatório)
 *   - "Valor aprovado" (numero, opcional)
 *   - "Data prevista" (data, opcional)
 *
 * Tipos suportados: texto, textarea, numero, data, selecao, checkbox
 *
 * Campos predefinidos (predefinido='S') são templates reutilizáveis
 * do sistema que podem ser habilitados por etapa.
 */
class SolicitacaoFluxoEtapaCampo extends Model
{
  protected $table = 'intranet_sol_fluxo_etapa_campos';

  protected $fillable = [
    'etapa_fluxo_id',
    'decisao_id',
    'label',
    'tipo',
    'placeholder',
    'opcoes',
    'obrigatorio',
    'ordem',
    'predefinido',
    'campo_predefinido_key',
  ];

  protected $casts = [
    'etapa_fluxo_id' => 'integer',
    'decisao_id'     => 'integer',
    'ordem'          => 'integer',
    'opcoes'         => 'array',
  ];

  // ─── Relacionamentos ───────────────────────────────────────

  /**
   * Etapa a qual este campo pertence.
   */
  public function etapa()
  {
    return $this->belongsTo(SolicitacaoFluxoEtapa::class, 'etapa_fluxo_id');
  }

  /**
   * Decisão à qual este campo está vinculado (null = campo da etapa toda).
   */
  public function decisao()
  {
    return $this->belongsTo(SolicitacaoFluxoDecisao::class, 'decisao_id');
  }

  /**
   * Valores preenchidos deste campo em todas as execuções.
   */
  public function valores()
  {
    return $this->hasMany(SolicitacaoFluxoEtapaCampoValor::class, 'etapa_campo_id');
  }

  // ─── Scopes ────────────────────────────────────────────────

  public function scopeOrdenados($query)
  {
    return $query->orderBy('ordem');
  }

  public function scopeObrigatorios($query)
  {
    return $query->where('obrigatorio', 'S');
  }

  public function scopePredefinidos($query)
  {
    return $query->where('predefinido', 'S');
  }

  // ─── Helpers ───────────────────────────────────────────────

  public function isObrigatorio(): bool
  {
    return $this->obrigatorio === 'S';
  }

  public function isPredefinido(): bool
  {
    return $this->predefinido === 'S';
  }

  /**
   * Retorna as opções do campo (quando tipo = 'selecao').
   */
  public function getOpcoes(): array
  {
    return $this->opcoes ?? [];
  }

  /**
   * Lista de tipos de campo suportados.
   */
  public static function tiposSuportados(): array
  {
    return [
      'texto'    => 'Texto',
      'textarea' => 'Texto Longo',
      'numero'   => 'Número',
      'data'     => 'Data',
      'selecao'  => 'Seleção',
      'checkbox' => 'Checkbox',
      'arquivo'  => 'Arquivo',
    ];
  }

  /**
   * Lista de campos predefinidos disponíveis no sistema.
   */
  public static function camposPredefinidos(): array
  {
    return [
      [
        'key'         => 'justificativa',
        'label'       => 'Justificativa',
        'tipo'        => 'textarea',
        'placeholder' => 'Informe a justificativa...',
        'obrigatorio' => 'S',
      ],
      [
        'key'         => 'valor_estimado',
        'label'       => 'Valor Estimado',
        'tipo'        => 'numero',
        'placeholder' => '0,00',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'data_prevista',
        'label'       => 'Data Prevista',
        'tipo'        => 'data',
        'placeholder' => 'dd/mm/aaaa',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'motivo_recusa',
        'label'       => 'Motivo da Recusa',
        'tipo'        => 'textarea',
        'placeholder' => 'Informe o motivo da recusa...',
        'obrigatorio' => 'S',
      ],
      [
        'key'         => 'observacao',
        'label'       => 'Observação',
        'tipo'        => 'textarea',
        'placeholder' => 'Observações adicionais...',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'numero_documento',
        'label'       => 'Nº Documento',
        'tipo'        => 'texto',
        'placeholder' => 'Número do documento',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'centro_custo',
        'label'       => 'Centro de Custo',
        'tipo'        => 'texto',
        'placeholder' => 'Centro de custo',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'aprovado',
        'label'       => 'Aprovado?',
        'tipo'        => 'checkbox',
        'placeholder' => null,
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'prioridade',
        'label'       => 'Prioridade',
        'tipo'        => 'selecao',
        'placeholder' => 'Selecione a prioridade',
        'obrigatorio' => 'N',
        'opcoes'      => ['Baixa', 'Média', 'Alta', 'Urgente'],
      ],
      [
        'key'         => 'prazo_entrega',
        'label'       => 'Prazo de Entrega',
        'tipo'        => 'data',
        'placeholder' => 'dd/mm/aaaa',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'fornecedor',
        'label'       => 'Fornecedor',
        'tipo'        => 'texto',
        'placeholder' => 'Nome do fornecedor',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'numero_nf',
        'label'       => 'Nº Nota Fiscal',
        'tipo'        => 'texto',
        'placeholder' => 'Número da nota fiscal',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'quantidade',
        'label'       => 'Quantidade',
        'tipo'        => 'numero',
        'placeholder' => '0',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'parecer',
        'label'       => 'Parecer Técnico',
        'tipo'        => 'textarea',
        'placeholder' => 'Descreva o parecer técnico...',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'comprovante',
        'label'       => 'Comprovante',
        'tipo'        => 'arquivo',
        'placeholder' => 'Selecione o arquivo...',
        'obrigatorio' => 'N',
      ],
      [
        'key'         => 'documento_anexo',
        'label'       => 'Documento Anexo',
        'tipo'        => 'arquivo',
        'placeholder' => 'Selecione o documento...',
        'obrigatorio' => 'N',
      ],
    ];
  }
}
