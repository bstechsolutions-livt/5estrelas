<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoSelecaoResposta extends Model
{
    protected $table = 'intranet_solicitacao_s_resp';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'itens_id',
        'assunto_id',
        'solicitacao_id',
        'selecao_id',
        'data1',
        'data2',
        'texto_resposta',
        'valor_winthor',
        'created_at',
        'file_id'
    ];

    // Relação: uma resposta pertence a um item
    public function item()
    {
        return $this->belongsTo(SolicitacaoSelecaoItem::class, 'itens_id');
    }

    // Relações adicionais podem ser definidas se houver models para `Assunto` e `Solicitacao`
    public function assunto()
    {
        return $this->belongsTo(SolicitacaoAssunto::class, 'assunto_id');
    }

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
    }

    public function selecao()
    {
        return $this->belongsTo(SolicitacaoSelecao::class, 'selecao_id');
    }

    public function file()
    {
        return $this->hasOne(File::class, 'id', 'file_id');
    }
}
