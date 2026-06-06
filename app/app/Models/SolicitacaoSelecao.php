<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoSelecao extends Model
{
    protected $table = 'intranet_solicitacao_sel';

    protected $primaryKey = 'id';

    protected $fillable = [
        'assunto_id',
        'label',
        'obrigatorio',
        'observacao',
        'tipo',
        'tipo_data',
        'dias_minimos',
        'multiplo',
        'exibir_nova',
        'exibir_atendimento',
        'ordem',
        'campo_pai_id',
        'valor_condicional',
        'created_at',
        'updated_at',
    ];

    // Relação: uma seleção tem muitos itens
    public function itens()
    {
        return $this->hasMany(SolicitacaoSelecaoItem::class, 'selecao_id');
    }

    // Relação opcional: se quiser associar com o assunto (caso haja model de assunto)
    public function assunto()
    {
        return $this->belongsTo(SolicitacaoAssunto::class, 'assunto_id');
    }

    // #12173 - Relação com campo pai para exibição condicional
    public function campoPai()
    {
        return $this->belongsTo(SolicitacaoSelecao::class, 'campo_pai_id');
    }

    // #12173 - Relação com campos filhos que dependem deste
    public function camposFilhos()
    {
        return $this->hasMany(SolicitacaoSelecao::class, 'campo_pai_id');
    }
}
